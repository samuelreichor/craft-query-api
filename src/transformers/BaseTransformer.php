<?php

namespace samuelreichoer\queryapi\transformers;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Addresses;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Color;
use craft\fields\Country;
use craft\fields\Entries;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\Tags;
use craft\fields\Users;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\events\RegisterFieldTransformersEvent;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\QueryApi;
use yii\base\InvalidConfigException;

abstract class BaseTransformer extends Component
{
    protected ElementInterface $element;
    public const EVENT_REGISTER_FIELD_TRANSFORMERS = 'registerTransformers';
    private array $customTransformers = [];

    private array $excludeFieldClasses;

    public function __construct(ElementInterface $element)
    {
        parent::__construct();
        $this->element = $element;
        $this->registerCustomTransformers();
        $this->excludeFieldClasses = $this->getExcludedFieldClasses();
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
        $fieldElements = array_merge($fieldLayout->getElementsByType(BaseField::class), $fieldLayout->getElementsByType(CustomField::class));
        $transformedFields = [];

        foreach ($fieldElements as $field) {
            $fieldClass = get_class($field);

            // only custom fields have the getField() method
            if (method_exists($field, 'getField')) {
                $field = $field->getField();
                $fieldClass = get_class($field);
            }

            $fieldHandle = $field->handle ?? $field->attribute ?? '';

            if ($fieldHandle === '') {
                continue;
            }

            // Check if fieldHandle is in predefinedFields or if predefinedFields is empty
            if (!empty($predefinedFields) && !in_array($fieldHandle, $predefinedFields, true)) {
                continue;
            }

            if (in_array($fieldClass, $this->excludeFieldClasses, true)) {
                continue;
            }

            $isSingleRelation = Utils::isSingleRelationField($field);
            try {
                $fieldValue = $this->element->getFieldValue($fieldHandle);
                $transformedFields[$fieldHandle] = $this->getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass);
            } catch (InvalidFieldException) {
                // handle native fields
                $fieldValue = $this->element->$fieldHandle;
                $transformedFields[$fieldHandle] = $this->transformNativeField($fieldValue, $fieldClass);
            }
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
            Addresses::class => $this->transformAddresses($fieldValue->all()),
            Assets::class => $this->transformAssets($fieldValue->all()),
            Categories::class => $this->transformCategories($fieldValue->all()),
            Color::class => $this->transformColor($fieldValue),
            Country::class => $this->transformCountry($fieldValue),
            Entries::class => $this->transformEntries($fieldValue->all()),
            Matrix::class => $this->transformMatrixField($fieldValue->all()),
            Link::class => $this->transformLinks($fieldValue),
            Tags::class => $this->transformTags($fieldValue->all()),
            Users::class => $this->transformUsers($fieldValue->all()),
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
                $isSingleRelation = Utils::isSingleRelationField($field);
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
    protected function transformAddresses(array $addresses): array
    {
        $transformedData = [];
        foreach ($addresses as $address) {
            $addressTransformer = new AddressTransformer($address);
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
    protected function getTransformedCustomFieldData($isSingleRelation, $fieldValue, $fieldClass)
    {
        if ($isSingleRelation) {
            $transformedValue = $this->transformCustomField($fieldValue, $fieldClass);
            return is_array($transformedValue) && !empty($transformedValue) ? $transformedValue[0] : null;
        }

        return $this->transformCustomField($fieldValue, $fieldClass);
    }

    protected function getExcludedFieldClasses(): array
    {
        if (isset(QueryApi::getInstance()->getSettings()->excludeFieldClasses)) {
            return array_merge(Constants::EXCLUDED_FIELD_HANDLES, QueryApi::getInstance()->getSettings()->excludeFieldClasses);
        }

        return Constants::EXCLUDED_FIELD_HANDLES;
    }
}
