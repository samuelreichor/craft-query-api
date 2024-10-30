<?php

namespace samuelreichoer\queryapi\services;

use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
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
    /**
     * Transforms an array of elements using the appropriate transformers.
     *
     * @param array $arrResult
     * @param array $predefinedFields
     * @return array
     * @throws InvalidFieldException
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function executeTransform(array $arrResult, array $predefinedFields = []): array
    {
        return array_map(function($element) use ($predefinedFields) {
            if (!$element) {
                return [];
            }
            $transformer = $this->getTransformerForElement($element);
            return $transformer->getTransformedData($predefinedFields);
        }, $arrResult);
    }

    /**
     * Determines the appropriate transformer for the given element.
     *
     * @param mixed $element
     * @return BaseTransformer
     * @throws Exception
     */
    private function getTransformerForElement(mixed $element): BaseTransformer
    {
        return match (true) {
            $element instanceof Entry => new EntryTransformer($element),
      $element instanceof Asset => new AssetTransformer($element),
      $element instanceof User => new UserTransformer($element),
      $element instanceof Address => new AddressTransformer($element),
      $element instanceof Category => new CategoryTransformer($element),
      $element instanceof Tag => new TagTransformer($element),
      default => throw new Exception('Unsupported element type'),
        };
    }
}
