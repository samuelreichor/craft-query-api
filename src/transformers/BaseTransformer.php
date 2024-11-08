<?php

namespace samuelreichoer\queryapi\transformers;

use craft\base\ElementInterface;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use yii\base\InvalidConfigException;

abstract class BaseTransformer
{
    protected ElementInterface $element;

    public function __construct(ElementInterface $element)
    {
        $this->element = $element;
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
        $metaData = $this->getMetaData();

        return array_merge($metaData, $transformedFields);
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

        $filteredOutClasses = [
        'nystudio107\seomatic\fields\SeoSettings',
    ];

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

            if (in_array($fieldClass, $filteredOutClasses, true)) {
                continue;
            }

            $transformedFields[$fieldHandle] = $this->transformNativeField($fieldValue, $fieldClass);
        }

        // Transform custom fields if they are in predefinedFields (if specified)
        foreach ($fields as $field) {
            $fieldHandle = $field->handle;

            // Check if fieldHandle is in predefinedFields or if predefinedFields is empty
            if (!empty($predefinedFields) && !in_array($fieldHandle, $predefinedFields, true)) {
                continue;
            }

            $fieldValue = $this->element->getFieldValue($fieldHandle);
            $fieldClass = get_class($field);

            if (in_array($fieldClass, $filteredOutClasses, true)) {
                continue;
            }

            $transformedFields[$fieldHandle] = $this->transformCustomField($fieldValue, $fieldClass);
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

        return match ($fieldClass) {
            'craft\fields\Assets' => $this->transformAssets($fieldValue->all()),
            'craft\fields\Matrix' => $this->transformMatrixField($fieldValue->all()),
            'craft\fields\Entries' => $this->transformEntries($fieldValue->all()),
            'craft\fields\Users' => $this->transformUsers($fieldValue->all()),
            'craft\fields\Categories' => $this->transformCategories($fieldValue->all()),
            'craft\fields\Tags' => $this->transformTags($fieldValue->all()),
            'craft\fields\Link' => $this->transformLinks($fieldValue),
            'craft\fields\Addresses' => $this->transformAddresses($fieldValue->all()),
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
        'id' => $this->element->id,
        'dateCreated' => $this->element->dateCreated,
        'dateUpdated' => $this->element->dateUpdated,
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
                $fieldClass = get_class($field);
                $blockData[$fieldHandle] = $this->transformCustomField($fieldValue, $fieldClass);
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
}
