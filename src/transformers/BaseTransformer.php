<?php

namespace samuelreichoer\queryapi\transformers;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\errors\FieldNotFoundException;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use craft\fields\Addresses;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Color;
use craft\fields\Country;
use craft\fields\Entries;
use craft\fields\Icon;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\Tags;
use craft\fields\Users;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\events\RegisterFieldTransformersEvent;
use samuelreichoer\queryapi\helpers\Fields;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\QueryApi;
use yii\base\InvalidConfigException;

abstract class BaseTransformer extends Component
{
    protected ElementInterface $element;
    public bool $includeFullEntry = false;
    public const EVENT_REGISTER_FIELD_TRANSFORMERS = 'registerTransformers';
    public array $predefinedFields = [];
    private array $customTransformers = [];
    private array $excludeFieldClasses;
    private bool $includeAll = false;

    private static array $fieldLayoutCache = [];
    private static ?array $excludeFieldClassesCache = null;

    public function __construct(ElementInterface $element, array $predefinedFields = [])
    {
        parent::__construct();
        $this->element = $element;
        $this->predefinedFields = $this->isFieldTree($predefinedFields)
            ? $predefinedFields
            : $this->getPredefinedFields($predefinedFields);
        $this->registerCustomTransformers();
        $this->excludeFieldClasses = $this->getExcludedFieldClasses();
    }

    /**
     * Transforms the element into an array.
     *
     * @return array
     */
    public function getTransformedData(): array
    {
        return [];
    }

    /**
     * Retrieves and transforms custom fields from the element.
     *
     * @return array
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    protected function getTransformedFields(): array
    {
        $layoutId = $this->element->getFieldLayout()?->id;

        if (!isset(self::$fieldLayoutCache[$layoutId])) {
            self::$fieldLayoutCache[$layoutId] = Fields::getAllFieldElementsByLayout($this->element->getFieldLayout());
        }

        $fieldElements = self::$fieldLayoutCache[$layoutId];

        $transformedFields = [];
        foreach ($fieldElements as $field) {
            // special case for generated Fields
            if (Fields::isGeneratedField($field)) {
                $this->handleGeneratedField($field, $transformedFields);
                continue;
            }

            // only custom fields have the getField() method
            $fieldClass = get_class($field);
            if (method_exists($field, 'getField')) {
                try {
                    $field = $field->getField();
                } catch (FieldNotFoundException $e) {
                    // sometimes trashed items are still in the layout but don't exist.
                    continue;
                }
                $fieldClass = get_class($field);
            }

            $fieldHandle = $field->handle ?? $field->attribute ?? '';

            if ($fieldHandle === '') {
                continue;
            }

            // we don't need to process the full element if predefined fields is not empty
            if (!empty($this->predefinedFields) && !array_key_exists($fieldHandle, $this->predefinedFields)) {
                continue;
            }

            if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                continue;
            }

            $isSingleRelation = Utils::isSingleRelationField($field);

            try {
                $fieldValue = $this->element->getFieldValue($fieldHandle);
                $subtree = $this->predefinedFields[$fieldHandle] ?? null;

                $transformedFields[$fieldHandle] = $this->getTransformedCustomFieldData(
                    $isSingleRelation,
                    $fieldValue,
                    $fieldClass,
                    $subtree
                );
            } catch (InvalidFieldException) {
                // handle native fields
                $fieldValue = $this->element->$fieldHandle;
                $transformedFields[$fieldHandle] = $this->transformNativeField($fieldValue, $fieldClass);
            }
        }

        return $transformedFields;
    }

    protected function handleGeneratedField(array $field, array &$transformedFields): ?array
    {
        $fieldHandle = Fields::getGeneratedFieldHandle($field);

        if ($this->includeAll === false && !empty($this->predefinedFields) && !array_key_exists($fieldHandle, $this->predefinedFields)) {
            return null;
        }

        $fieldClass = Fields::getGeneratedFieldUid($field);

        if ($this->element->canGetProperty($fieldHandle)) {
            $value = $this->transformNativeField($this->element->$fieldHandle, $fieldClass);
            $transformedFields[$fieldHandle] = $value;
            return $transformedFields;
        }

        Craft::error('Generated Field has invalid handle: ' . $fieldHandle, 'queryApi');
        return null;
    }

    /**
     * Transforms a custom field based on its class.
     *
     * @param mixed $fieldValue
     * @param string $fieldClass
     * @param array|null $subtree
     * @return mixed
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    protected function transformCustomField(mixed $fieldValue, string $fieldClass, ?array $subtree = null): mixed
    {
        if (!$fieldValue || !$fieldClass) {
            return null;
        }

        // Check for custom transformers from EVENT_REGISTER_FIELD_TRANSFORMERS
        foreach ($this->customTransformers as $customTransformer) {
            if ($customTransformer['fieldClass'] === $fieldClass) {
                $transformerClass = $customTransformer['transformer'];

                if (!class_exists($transformerClass)) {
                    Craft::error("Transformer class {$transformerClass} not found.", 'queryApi');
                    break;
                }

                $transformer = new $transformerClass($fieldValue);

                if (!method_exists($transformer, 'getTransformedData')) {
                    Craft::error("Transformer {$transformerClass} does not have a 'getTransformedData' method.", 'queryApi');
                    return null;
                }

                return $transformer->getTransformedData();
            }
        }

        return match ($fieldClass) {
            Addresses::class => $this->transformAddresses($fieldValue->all(), $subtree),
            Assets::class => $this->transformAssets($fieldValue->all(), $subtree),
            Categories::class => $this->transformCategories($fieldValue->all(), $subtree),
            Color::class => $this->transformColor($fieldValue),
            'craft\fields\ContentBlock' => $this->transformContentBlock($fieldValue, $subtree),
            Country::class => $this->transformCountry($fieldValue),
            Entries::class => $this->transformEntries($fieldValue->all(), $subtree),
            Icon::class => $this->transformIcon($fieldValue),
            Matrix::class => $this->transformMatrixField($fieldValue->all(), $subtree),
            Link::class => $this->transformLinks($fieldValue),
            Tags::class => $this->transformTags($fieldValue->all(), $subtree),
            Users::class => $this->transformUsers($fieldValue->all(), $subtree),
            default => $fieldValue,
        };
    }

    /**
     * Transforms a native field based on its class.
     *
     * @param mixed $fieldValue
     * @param string $fieldClass
     * @return mixed
     */
    protected function transformNativeField(mixed $fieldValue, string $fieldClass): mixed
    {
        if (!$fieldValue || !$fieldClass) {
            return null;
        }

        switch ($fieldClass) {
            case 'craft\fieldlayoutelements\users\PhotoField':
                $assetTransformer = new AssetTransformer($fieldValue);
                $this->inheritVars($assetTransformer);
                return $assetTransformer->getTransformedData();

            default:
                return $fieldValue;
        }
    }

    /**
     * Retrieves metadata from the element.
     *
     * @return array
     */
    protected function getMetaData(): array
    {
        return [
            'id' => $this->element->getId(),
        ];
    }

    /**
     * Transforms a Matrix field.
     *
     * @param array $matrixFields
     * @param array|null $subtree
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    protected function transformMatrixField(array $matrixFields, ?array $subtree = null): array
    {
        $transformedData = [];

        foreach ($matrixFields as $block) {
            $blockData = $this->getBlockData($block, $subtree);
            $blockData['type'] = $block->type->handle;

            if ($block->title) {
                $blockData['title'] = $block->title;
            }

            $transformedData[] = $blockData;
        }

        return $transformedData;
    }

    /**
     * @throws InvalidFieldException
     * @throws ImageTransformException
     * @throws InvalidConfigException
     */
    protected function transformContentBlock(mixed $block, ?array $subtree = null): array
    {
        return $this->getBlockData($block, $subtree);
    }

    /**
     * @throws InvalidFieldException
     * @throws InvalidConfigException
     * @throws ImageTransformException
     */
    protected function getBlockData(mixed $block, ?array $subtree = null): array
    {
        $blockData = [];

        foreach ($block->getFieldValues() as $fieldHandle => $fieldValue) {
            if (!empty($subtree) && !array_key_exists($fieldHandle, $subtree)) {
                continue;
            }

            $field = $block->getFieldLayout()->getFieldByHandle($fieldHandle);

            $isSingleRelation = Utils::isSingleRelationField($field);
            $fieldClass = get_class($field);

            // Exclude fields in matrix blocks
            if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                continue;
            }

            $fieldSubtree = $subtree[$fieldHandle] ?? null;
            $blockData[$fieldHandle] = $this->getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass, $fieldSubtree);
        }
        return $blockData;
    }

    /**
     * Transforms an array of Asset elements.
     *
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    protected function transformAssets(array $assets, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($assets as $asset) {
            $assetTransformer = new AssetTransformer($asset, $subtree ?? []);
            $this->inheritVars($assetTransformer);
            $transformedData[] = $assetTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Entry elements.
     *
     * @param array $entries
     * @param array|null $subtree
     * @return array
     */
    protected function transformEntries(array $entries, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($entries as $entry) {
            if ($this->includeFullEntry) {
                $entryTransformer = new EntryTransformer($entry, $subtree ?? []);
                $this->inheritVars($entryTransformer);
                $transformedData[] = $entryTransformer->getTransformedData();
            } else {
                $transformedData[] = [
                    'title' => $entry->title,
                    'slug' => $entry->slug,
                    'url' => $entry->url,
                    'id' => $entry->id,
                ];
            }
        }
        return $transformedData;
    }

    /**
     * Transforms an array of User elements.
     *
     * @param array $users
     * @param array|null $subtree
     * @return array
     */
    protected function transformUsers(array $users, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($users as $user) {
            $userTransformer = new UserTransformer($user, $subtree ?? []);
            $this->inheritVars($userTransformer);
            $transformedData[] = $userTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Category elements.
     *
     * @param array $categories
     * @param array|null $subtree
     * @return array
     */
    protected function transformCategories(array $categories, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($categories as $category) {
            $categoryTransformer = new CategoryTransformer($category, $subtree ?? []);
            $this->inheritVars($categoryTransformer);
            $transformedData[] = $categoryTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Tag elements.
     *
     * @param array $tags
     * @param array|null $subtree
     * @return array
     */
    protected function transformTags(array $tags, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($tags as $tag) {
            $tagTransformer = new TagTransformer($tag, $subtree ?? []);
            $this->inheritVars($tagTransformer);
            $transformedData[] = $tagTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms a Link field.
     *
     * @param mixed $link
     * @return array
     */
    protected function transformLinks(mixed $link): array
    {
        if (empty($link)) {
            return [];
        }

        return [
            'elementType' => $link->type,
            'url' => $link->url,
            'label' => $link->label,
            'target' => $link->target ?? '_self',
            'rel' => $link->rel,
            'urlSuffix' => $link->urlSuffix,
            'class' => $link->class,
            'id' => $link->id,
            'ariaLabel' => $link->ariaLabel,
            'download' => $link->download,
            'downloadFile' => $link->filename,
        ];
    }

    /**
     * Transforms an array of User elements.
     *
     * @param array $addresses
     * @return array
     */
    protected function transformAddresses(array $addresses, ?array $subtree = null): array
    {
        $transformedData = [];
        foreach ($addresses as $address) {
            $addressTransformer = new AddressTransformer($address, $subtree ?? []);
            $this->inheritVars($addressTransformer);
            $transformedData[] = $addressTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms a color element
     *
     * @param mixed $color
     * @return array
     */
    protected function transformColor(mixed $color): array
    {
        if (empty($color)) {
            return [];
        }

        return [
            'hex' => $color->getHex(),
            'rgb' => $color->getRgb(),
            'hsl' => $color->getHsl(),
        ];
    }

    /**
     * Transforms a country element
     *
     * @param mixed $country
     * @return array
     */
    protected function transformCountry(mixed $country): array
    {
        if (empty($country)) {
            return [];
        }

        return [
            'name' => $country->getName(),
            'countryCode' => $country->getCountryCode(),
            'threeLetterCode' => $country->getThreeLetterCode(),
            'locale' => $country->getLocale(),
            'currencyCode' => $country->getCurrencyCode(),
            'timezones' => $country->getTimezones(),
        ];
    }

    /**
     * Transforms a icon element
     *
     * @param mixed $icon
     * @return array
     */
    protected function transformIcon(mixed $icon): array
    {
        if (empty($icon)) {
            return [];
        }

        return [$icon->name];
    }

    /**
     * Loads custom transformers via event.
     */
    protected function registerCustomTransformers(): void
    {
        if ($this->hasEventHandlers(self::EVENT_REGISTER_FIELD_TRANSFORMERS)) {
            $event = new RegisterFieldTransformersEvent();
            $this->trigger(self::EVENT_REGISTER_FIELD_TRANSFORMERS, $event);
            $this->customTransformers = $event->transformers;
        }
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    protected function getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass, ?array $subtree = null)
    {
        if ($isSingleRelation) {
            $transformedValue = $this->transformCustomField($fieldValue, $fieldClass, $subtree);
            return is_array($transformedValue) && !empty($transformedValue) ? $transformedValue[0] : null;
        }

        return $this->transformCustomField($fieldValue, $fieldClass, $subtree);
    }

    protected function getExcludedFieldClasses(): array
    {
        if (self::$excludeFieldClassesCache === null) {
            if (isset(QueryApi::getInstance()->getSettings()->excludeFieldClasses)) {
                self::$excludeFieldClassesCache = array_merge(
                    Constants::EXCLUDED_FIELD_CLASSES,
                    QueryApi::getInstance()->getSettings()->excludeFieldClasses
                );
            } else {
                self::$excludeFieldClassesCache = Constants::EXCLUDED_FIELD_CLASSES;
            }
        }

        return self::$excludeFieldClassesCache;
    }

    protected function isFieldTree(array $fields): bool
    {
        if (empty($fields)) {
            return false;
        }

        // If first key is numeric, it's a list of paths
        $firstKey = array_key_first($fields);
        return !is_numeric($firstKey);
    }

    /**
     * Builds the selection tree from the given field paths.
     * Example:
     *   ['entries', 'entries.title', 'another']
     *   becomes
     *   { entries: { title: null }, another: null }
     *
     * Special case:
     *   '*' only sets includeAll=true, but is NOT added to the tree.
     */
    protected function getPredefinedFields(array $predefinedFields): array
    {
        // Reset includeAll for every call
        $this->includeAll = false;

        /** @var array<string, mixed> $tree */
        $tree = [];

        foreach ($predefinedFields as $path) {
            if (!is_string($path)) {
                continue;
            }

            $path = trim($path);

            // If "*" is present -> mark includeAll, skip adding to tree
            if ($path === '*') {
                $this->includeAll = true;
                continue;
            }

            // Split path by "." into nested parts
            $parts = explode('.', $path);
            $current = &$tree;

            // Build nested arrays for each part
            foreach ($parts as $part) {
                $current[$part] ??= [];
                $current = &$current[$part];
            }

            $current = null;
            unset($current);
        }

        return $tree;
    }

    /**
     * Filters $data according to the prepared tree ($tree).
     *
     * Behavior:
     * - includeAll = false (Pick mode): Only fields in the tree are kept.
     * - includeAll = true (All + Overrides mode): Return everything,
     *   but replace subtrees from $tree with a reduced "Pick" result (via pickOnly()).
     *
     * @param mixed      $data The input data (scalar, assoc array, list, or null)
     * @param array|null $tree The selection tree to apply
     * @return mixed The filtered data
     */
    protected function filterByPredefinedFields(mixed $data, ?array $tree): mixed
    {
        // Case 1: no tree and no includeAll -> always return data as-is
        if (empty($tree) && $this->includeAll === false) {
            return $data;
        }

        // Case 2: data is null -> always return null
        if ($data === null) {
            return null;
        }

        // Case 3: scalar values (string, int, etc.) -> cannot filter further
        if (!is_array($data)) {
            return $data;
        }

        // ---------- Case A: includeAll = false ("Pick" mode) ----------
        if ($this->includeAll === false) {
            $out = [];

            // Associative array (object-like)
            if ($this->isAssoc($data)) {
                // Always only keep keys that exist in $tree
                foreach ($tree as $key => $subTree) {
                    if (array_key_exists($key, $data)) {
                        // Always recurse into subtree
                        $out[$key] = $this->filterByPredefinedFields($data[$key], $subTree);
                    }
                }
                return $out;
            }

            // Numeric array (list) -> apply subtree to each item
            foreach ($data as $item) {
                $out[] = $this->filterByPredefinedFields($item, $tree);
            }
            return $out;
        }

        // ---------- Case B: includeAll = true ("All + Overrides" mode) ----------
        // Start by copying full data
        $out = $data;

        // Associative array
        if ($this->isAssoc($data)) {
            foreach ($tree ?? [] as $key => $subTree) {
                if (!array_key_exists($key, $data)) {
                    // Always ignore missing keys
                    continue;
                }
                // Important: for this subtree we force Pick mode
                $out[$key] = $this->pickOnly($data[$key], $subTree);
            }
            return $out;
        }

        // Numeric array (list) -> apply Pick mode per item
        return array_map(function($item) use ($tree) {
            return $this->pickOnly($item, $tree);
        }, $data);
    }

    /**
     * Forces "Pick" mode once (ignores global includeAll=true).
     * This is used when includeAll is active, but we still need
     * to reduce a subtree to only the fields listed in $tree.
     *
     * Steps:
     * - Temporarily disable includeAll
     * - Run filterByPredefinedFields() in Pick mode
     * - Restore a previous includeAll flag
     */
    protected function pickOnly($data, ?array $tree)
    {
        $old = $this->includeAll;              // Remember current flag
        $this->includeAll = false;             // Force Pick mode
        $res = $this->filterByPredefinedFields($data, $tree);
        $this->includeAll = $old;              // Restore flag
        return $res;
    }

    protected function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function smartFilter(array $data, array $defaultKeys): array
    {
        // If no filtering is needed, return original data
        if (empty($this->predefinedFields)) {
            return $data;
        }

        // If hard pick is enabled, no default keys are required and the whole data object gets filtered.
        if (QueryApi::getInstance()->getSettings()->hardPick) {
            return $this->filterByPredefinedFields($data, $this->predefinedFields);
        }

        // Separate default data from other data
        $defaultData = array_intersect_key($data, array_flip($defaultKeys));
        $otherData = array_diff_key($data, array_flip($defaultKeys));

        // Filter "other" data based on fields that are NOT default fields
        $otherTree = array_diff_key($this->predefinedFields, array_flip($defaultKeys));
        $filteredOtherData = $this->filterByPredefinedFields($otherData, $otherTree);

        // Handle default data: keep all of it, but filter parts that are in the tree
        $finalDefaultData = [];
        foreach ($defaultData as $key => $value) {
            if (array_key_exists($key, $this->predefinedFields)) {
                // If a default key is in the filter tree, filter it
                $finalDefaultData[$key] = $this->filterByPredefinedFields($value, $this->predefinedFields[$key]);
            } else {
                // Otherwise, keep the default value as is
                $finalDefaultData[$key] = $value;
            }
        }

        // Always include default data, and merge filtered other data
        return array_merge($finalDefaultData, $filteredOtherData);
    }

    protected function inheritVars($transformer): void
    {
        $transformer->includeFullEntry = $this->includeFullEntry;
    }
}
