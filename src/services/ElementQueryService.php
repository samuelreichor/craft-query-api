<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\Matrix;
use Exception;
use samuelreichoer\queryapi\events\RegisterElementTypesEvent;
use samuelreichoer\queryapi\helpers\Utils;
use samuelreichoer\queryapi\QueryApi;

class ElementQueryService extends Component
{
    public const EVENT_REGISTER_ELEMENT_TYPES = 'registerElementTypes';

    public array $customTransformer = [];

    public array $customElementTypes = [];

    private array $elementTypeMap = [
        'addresses' => Address::class,
        'assets' => Asset::class,
        'entries' => Entry::class,
        'users' => User::class,
    ];

    private array $allowedMethods = [
        'addresses' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'addressLine1', 'addressLine2', 'addressLine3', 'locality', 'organization', 'fullName'],
        'assets' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'volume', 'kind', 'filename', 'site', 'siteId'],
        'entries' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'slug', 'uri', 'section', 'postDate', 'site', 'siteId', 'level', 'sectionId', 'type'],
        'users' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'group', 'groupId', 'authorOf', 'email', 'fullName', 'hasPhoto'],
    ];

    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->registerCustomElementType();
    }

    /**
     * Handles the query execution for all element types.
     * @throws Exception
     */
    public function executeQuery(string $elementType, array $params): array
    {
        // Set cache duration of config and fallback to general craft cache duration
        if (isset(QueryApi::getInstance()->getSettings()->cacheDuration)) {
            $duration = QueryApi::getInstance()->getSettings()->cacheDuration;
        } else {
            $duration = Craft::$app->getConfig()->getGeneral()->cacheDuration;
        }

        $hashedParamsKey = Utils::generateCacheKey($params);
        $cacheKey = 'queryapi_' . $elementType . '_' . $hashedParamsKey;

        if ($result = Craft::$app->getCache()->get($cacheKey)) {
            return $result;
        }

        Craft::$app->getElements()->startCollectingCacheInfo();

        $query = $this->handleQuery($elementType, $params);

        $queryOne = isset($params['one']) && $params['one'] === '1';
        $queryAll = isset($params['all']) && $params['all'] === '1';

        if (!$queryAll && !$queryOne) {
            throw new Exception('No query was executed. This is usually because .one() or .all() is missing in the query');
        }

        $eagerloadingMap = $this->getEagerLoadingMap();
        $query->with($eagerloadingMap);
        $queriedData = $queryOne ? [$query->one()] : $query->all();

        $cacheInfo = Craft::$app->getElements()->stopCollectingCacheInfo();

        Craft::$app->getCache()->set(
            $cacheKey,
            $queriedData,
            $duration,
            $cacheInfo[0]
        );
        return $queriedData;
    }

    /**
     * Handles building queries based on element type and parameters.
     * @throws Exception
     */
    public function handleQuery(string $elementType, array $params)
    {
        $query = $this->elementTypeMap[$elementType]::find() ?? throw new Exception('Query for this element type is not yet implemented');
        $allowedMethods = $this->getAllowedMethods($elementType);
        return $this->applyParamsToQuery($query, $params, $allowedMethods);
    }

    /**
     * Apply parameters to the query based on allowed methods.
     */
    private function applyParamsToQuery($query, array $params, array $allowedMethods)
    {
        foreach ($params as $key => $value) {
            if (in_array($key, $allowedMethods)) {
                $query->$key($value);
            }
        }

        return $query;
    }

    /**
     * Returns the allowed methods for the given element type.
     *
     * @param string $elementType
     * @return array
     * @throws Exception
     */
    private function getAllowedMethods(string $elementType): array
    {
        if (!isset($this->allowedMethods[$elementType])) {
            throw new Exception('Unknown element type: ' . $elementType);
        }

        return $this->allowedMethods[$elementType];
    }

    public function getEagerLoadingMap(): array
    {
        $mapKey = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($keys = $this->_getEagerLoadingMapForField($field)) {
                $mapKey[] = $keys;
            }
        }

        return array_merge(...$mapKey);
    }

    public function getCustomTransformers(): array
    {
        return $this->customTransformer;
    }

    public function getCustomElementTypes(): array
    {
        return $this->customElementTypes;
    }

    private function _getEagerLoadingMapForField(FieldInterface $field, ?string $prefix = null, int $iteration = 0): array
    {
        $keys = [];

        if ($field instanceof Matrix) {
            if ($iteration > 5) {
                return [];
            }

            $iteration++;

            // Because Matrix fields can be infinitely nested, we need to short-circuit things to prevent infinite looping.
            $keys[] = $prefix . $field->handle;

            foreach ($field->getEntryTypes() as $entryType) {
                foreach ($entryType->getCustomFields() as $subField) {
                    $nestedKeys = $this->_getEagerLoadingMapForField($subField, $prefix . $field->handle . '.' . $entryType->handle . ':', $iteration);

                    if ($nestedKeys) {
                        $keys = array_merge($keys, $nestedKeys);
                    }
                }
            }
        }

        if ($field instanceof BaseRelationField) {
            $keys[] = $prefix . $field->handle;
        }

        return $keys;
    }

    /**
     * Register custom element types through event
     *
     * @throws Exception
     */
    private function registerCustomElementType(): void
    {
        if ($this->hasEventHandlers(self::EVENT_REGISTER_ELEMENT_TYPES)) {
            $event = new RegisterElementTypesEvent();
            $this->trigger(self::EVENT_REGISTER_ELEMENT_TYPES, $event);
            $customElementTypes = $event->elementTypes;

            $customElementTypeMap = [];
            $customElementTypeMethodsMap = [];
            $customElementTypeTransformersMap = [];

            foreach ($customElementTypes as $customType) {
                // Validate required properties and add them to maps
                $this->validateCustomElementType($customType);

                // Make custom element type globally available
                $this->customElementTypes[$customType->elementTypeHandle] = $customType->elementTypeClass;
                
                // Build custom query map
                $customElementTypeMap[$customType->elementTypeHandle] = $customType->elementTypeClass;

                // Build allowed methods map
                $customElementTypeMethodsMap[$customType->elementTypeHandle] = $customType->allowedMethods;

                // Add the transformer map
                $customElementTypeTransformersMap[$customType->elementTypeClass] = $customType->transformer;
            }

            // Merge custom configurations
            $this->elementTypeMap = $customElementTypeMap + $this->elementTypeMap;
            $this->allowedMethods = $customElementTypeMethodsMap + $this->allowedMethods;
            $this->customTransformer = $customElementTypeTransformersMap + $this->customTransformer;
        }
    }

    /**
     * Validate custom element type for proper registration
     * @throws Exception
     */
    private function validateCustomElementType($customType): void
    {
        $requiredProperties = ['elementTypeClass', 'elementTypeHandle', 'allowedMethods', 'transformer'];
        foreach ($requiredProperties as $property) {
            if (!property_exists($customType, $property) || !$customType->$property) {
                throw new Exception("Missing $property property in custom element type: " . json_encode($customType));
            }
        }

        // Validate class and transformer existence
        if (!class_exists($customType->elementTypeClass)) {
            throw new Exception("Class {$customType->elementTypeClass} is not defined.");
        }
        if (!class_exists($customType->transformer)) {
            throw new Exception("Transformer class {$customType->transformer} is not defined.");
        }
    }
}
