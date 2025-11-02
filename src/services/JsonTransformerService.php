<?php

namespace samuelreichoer\queryapi\services;

use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use Exception;
use samuelreichoer\queryapi\transformers\AddressTransformer;
use samuelreichoer\queryapi\transformers\AssetTransformer;
use samuelreichoer\queryapi\transformers\BaseTransformer;
use samuelreichoer\queryapi\transformers\CategoryTransformer;
use samuelreichoer\queryapi\transformers\EntryTransformer;
use samuelreichoer\queryapi\transformers\TagTransformer;
use samuelreichoer\queryapi\transformers\UserTransformer;
use yii\base\InvalidConfigException;

class JsonTransformerService
{
    private array $transformers;
    private ElementQueryService $elementQueryService;
    public bool $isFullEntryData = false;

    public function __construct(ElementQueryService $elementQueryService)
    {
        $this->elementQueryService = $elementQueryService;
        $this->transformers = $this->elementQueryService->getCustomTransformers();
    }

    /**
     * Transforms an array of elements using the appropriate transformers.
     *
     * @param array $arrResult
     * @param array $predefinedFields
     * @param bool $fullEntryData
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     * @throws Exception
     */
    public function executeTransform(array $arrResult, array $predefinedFields = [], bool $fullEntryData = false): array
    {
        return array_map(function($element) use ($predefinedFields, $fullEntryData) {
            if (!$element) {
                return [];
            }
            $transformer = $this->getTransformerForElement($element, $predefinedFields);

            /**
             * Set $includeFullEntry on transformer to enable access through inheritance on all transformers
             * without introducing a breaking change.
             */
            $transformer->includeFullEntry = $fullEntryData;
            return $transformer->getTransformedData();
        }, $arrResult);
    }

    /**
     * Determines the appropriate transformer for the given element.
     *
     * @param mixed $element
     * @return BaseTransformer
     * @throws Exception
     */
    private function getTransformerForElement(mixed $element, array $predefinedFields): BaseTransformer
    {
        // Register custom transformers for custom element types
        if (count($this->transformers) > 0) {
            $elementTypeHandle = get_class($element);
            if (isset($this->transformers[$elementTypeHandle])) {
                $transformerClass = $this->transformers[$elementTypeHandle];
                return new $transformerClass($element, $predefinedFields);
            }
        }

        return match (true) {
            $element instanceof Entry => new EntryTransformer($element, $predefinedFields),
            $element instanceof Asset => new AssetTransformer($element, $predefinedFields),
            $element instanceof User => new UserTransformer($element, $predefinedFields),
            $element instanceof Address => new AddressTransformer($element, $predefinedFields),
            $element instanceof Category => new CategoryTransformer($element, $predefinedFields),
            $element instanceof Tag => new TagTransformer($element, $predefinedFields),
            default => throw new Exception('Unsupported element type'),
        };
    }
}
