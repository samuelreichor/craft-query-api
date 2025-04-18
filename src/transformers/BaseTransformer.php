<?php

namespace samuelreichoer\queryapi\transformers;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use samuelreichoer\queryapi\events\RegisterFieldTransformersEvent;
use samuelreichoer\queryapi\QueryApi;
use yii\base\InvalidConfigException;

abstract class BaseTransformer extends Component
{
    protected ElementInterface $element;
    public const EVENT_REGISTER_FIELD_TRANSFORMERS = 'registerTransformers';
    private array $customTransformers = [];

    private array $excludeFieldClasses = ['nystudio107\seomatic\fields\SeoSettings'];

    public function __construct(ElementInterface $element)
    {
        parent::__construct();
        $this->element = $element;
        $this->registerCustomTransformers();

        if (isset(QueryApi::getInstance()->getSettings()->excludeFieldClasses)) {
            $this->excludeFieldClasses = array_merge($this->excludeFieldClasses, QueryApi::getInstance()->getSettings()->excludeFieldClasses);
        }
    }

    /**
     * Transforms the element into an array.
     *
     * @param array $predefinedFields
     * @return array
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    public function getTransformedData(array $predefinedFields = []): array
    {
        $transformedFields = $this->getTransformedFields($predefinedFields);
        $metadata = $this->getMetaData();

        return array_merge($metadata, $transformedFields);
    }

    /**
     * Retrieves and transforms custom fields from the element.
     *
     * @param array $predefinedFields
     * @return array
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    protected function getTransformedFields(array $predefinedFields = []): array
    {
        $fieldLayout = $this->element->getFieldLayout();
        $fields = $fieldLayout ? $fieldLayout->getCustomFields() : [];
        $nativeFields = $fieldLayout ? $fieldLayout->getAvailableNativeFields() : [];
        $transformedFields = [];

        // Transform native fields if they are in predefinedFields (if specified)
        foreach ($nativeFields as $nativeField) {
            $fieldHandle = $nativeField->attribute ?? '';

            if ($fieldHandle === '') {
                continue;
            }

            // Check if fieldHandle is in predefinedFields or if predefinedFields is empty
            if (!empty($predefinedFields) && !in_array($fieldHandle, $predefinedFields, true)) {
                continue;
            }

            $fieldValue = $this->element->$fieldHandle;
            $fieldClass = get_class($nativeField);

            if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                continue;
            }

            $transformedFields[$fieldHandle] = $this->transformNativeField($fieldValue, $fieldClass);
        }

        // Transform custom fields if they are in predefinedFields (if specified)
        foreach ($fields as $field) {
            $fieldHandle = $field->handle;

            // Check if field has a limit of relations
            $isSingleRelation = $this->isSingleRelationField($field);

            // Check if fieldHandle is in predefinedFields or if predefinedFields is empty
            if (!empty($predefinedFields) && !in_array($fieldHandle, $predefinedFields, true)) {
                continue;
            }

            $fieldValue = $this->element->getFieldValue($fieldHandle);
            $fieldClass = get_class($field);
            if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                continue;
            }

            $transformedFields[$fieldHandle] = $this->getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass);
        }

        return $transformedFields;
    }

    /**
     * Transforms a custom field based on its class.
     *
     * @param mixed $fieldValue
     * @param string $fieldClass
     * @return mixed
     * @throws InvalidFieldException|InvalidConfigException
     * @throws ImageTransformException
     */
    protected function transformCustomField(mixed $fieldValue, string $fieldClass): mixed
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
            'craft\fields\Addresses' => $this->transformAddresses($fieldValue->all()),
            'craft\fields\Assets' => $this->transformAssets($fieldValue->all()),
            'craft\fields\Categories' => $this->transformCategories($fieldValue->all()),
            'craft\fields\Color' => $this->transformColor($fieldValue),
            'craft\fields\Country' => $this->transformCountry($fieldValue),
            'craft\fields\Entries' => $this->transformEntries($fieldValue->all()),
            'craft\fields\Matrix' => $this->transformMatrixField($fieldValue->all()),
            'craft\fields\Link' => $this->transformLinks($fieldValue),
            'craft\fields\Tags' => $this->transformTags($fieldValue->all()),
            'craft\fields\Users' => $this->transformUsers($fieldValue->all()),
            default => $fieldValue,
        };
    }

    /**
     * Transforms a native field based on its class.
     *
     * @param mixed  $fieldValue
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
     * @return array
     * @throws InvalidFieldException|InvalidConfigException|ImageTransformException
     */
    protected function transformMatrixField(array $matrixFields): array
    {
        $transformedData = [];

        foreach ($matrixFields as $block) {
            $blockData = [
                'type' => $block->type->handle,
            ];

            if ($block->title) {
                $blockData['title'] = $block->title;
            }

            foreach ($block->getFieldValues() as $fieldHandle => $fieldValue) {
                $field = $block->getFieldLayout()->getFieldByHandle($fieldHandle);

                // Check if field has a limit of relations
                $isSingleRelation = $this->isSingleRelationField($field);
                $fieldClass = get_class($field);

                // Exclude fields in matrix blocks
                if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                    continue;
                }

                $blockData[$fieldHandle] = $this->getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass);
            }

            $transformedData[] = $blockData;
        }

        return $transformedData;
    }

    /**
     * Transforms an array of Asset elements.
     *
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    protected function transformAssets(array $assets): array
    {
        $transformedData = [];
        foreach ($assets as $asset) {
            $assetTransformer = new AssetTransformer($asset);
            $transformedData[] = $assetTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Entry elements.
     *
     * @param array $entries
     * @return array
     */
    protected function transformEntries(array $entries): array
    {
        $transformedData = [];
        foreach ($entries as $entry) {
            $transformedData[] = [
                'title' => $entry->title,
                'slug' => $entry->slug,
                'url' => $entry->url,
                'id' => $entry->id,
            ];
        }
        return $transformedData;
    }

    /**
     * Transforms an array of User elements.
     *
     * @param array $users
     * @return array
     */
    protected function transformUsers(array $users): array
    {
        $transformedData = [];
        foreach ($users as $user) {
            $userTransformer = new UserTransformer($user);
            $transformedData[] = $userTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Category elements.
     *
     * @param array $categories
     * @return array
     */
    protected function transformCategories(array $categories): array
    {
        $transformedData = [];
        foreach ($categories as $category) {
            $categoryTransformer = new CategoryTransformer($category);
            $transformedData[] = $categoryTransformer->getTransformedData();
        }
        return $transformedData;
    }

    /**
     * Transforms an array of Tag elements.
     *
     * @param array $tags
     * @return array
     */
    protected function transformTags(array $tags): array
    {
        $transformedData = [];
        foreach ($tags as $tag) {
            $tagTransformer = new TagTransformer($tag);
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
            'value' => $link->value,
        ];
    }

    /**
     * Transforms an array of User elements.
     *
     * @param array $addresses
     * @return array
     */
    protected function transformAddresses(array $addresses): array
    {
        $transformedData = [];
        foreach ($addresses as $address) {
            $userTransformer = new AddressTransformer($address);
            $transformedData[] = $userTransformer->getTransformedData();
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

    protected function isSingleRelationField($field): bool
    {
        return (property_exists($field, 'maxEntries') && $field->maxEntries == 1)
            || (property_exists($field, 'maxRelations') && $field->maxRelations == 1)
            || (property_exists($field, 'maxAddresses') && $field->maxAddresses == 1);
    }

    /**
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws ImageTransformException
     */
    protected function getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass)
    {
        if ($isSingleRelation) {
            $transformedValue = $this->transformCustomField($fieldValue, $fieldClass);
            return is_array($transformedValue) && !empty($transformedValue) ? $transformedValue[0] : null;
        }

        return $this->transformCustomField($fieldValue, $fieldClass);
    }
}
