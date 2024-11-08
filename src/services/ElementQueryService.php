<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\FieldInterface;
use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\fields\BaseRelationField;
use craft\fields\Matrix;
use craft\helpers\App;
use Exception;
use samuelreichoer\queryapi\helpers\Utils;

class ElementQueryService
{
    private array $allowedDefaultMethods = ['limit', 'id', 'status', 'offset', 'orderBy'];

    private array $allowedMethods = [
        'addresses' => ['addressLine1', 'addressLine2', 'addressLine3', 'locality', 'organization', 'fullName'],
        'assets' => ['volume', 'kind', 'filename', 'site', 'siteId'],
        'entries' => ['slug', 'uri', 'section', 'postDate', 'site', 'siteId'],
        'users' => ['group', 'groupId', 'authorOf', 'email', 'fullName', 'hasPhoto'],
    ];

    /**
     * Handles the query execution for all element types.
     * @throws Exception
     */
    public function executeQuery(string $elementType, array $params): array
    {
        $craftDuration = Craft::$app->getConfig()->getGeneral()->cacheDuration;
        $duration = App::env('CRAFT_ENVIRONMENT') === 'dev' ? 0 : $craftDuration;
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
        // Get the query object based on element type
        $query = match ($elementType) {
            'addresses' => Address::find(),
            'assets' => Asset::find(),
            'entries' => Entry::find(),
            'users' => User::find(),
            default => throw new Exception('Query for this element type is not yet implemented'),
        };

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

        return array_merge($this->allowedDefaultMethods, $this->allowedMethods[$elementType]);
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
}
