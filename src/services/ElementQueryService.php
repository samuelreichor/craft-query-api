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
use samuelreichoer\queryapi\helpers\Permissions;
use samuelreichoer\queryapi\models\QueryApiSchema;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

class ElementQueryService extends Component
{
    public const EVENT_REGISTER_ELEMENT_TYPES = 'registerElementTypes';

    public array $customTransformer = [];

    public array $customElementTypes = [];

    private QueryApiSchema $schema;

    private array $elementTypeMap = [
        'addresses' => Address::class,
        'assets' => Asset::class,
        'entries' => Entry::class,
        'users' => User::class,
    ];

    private array $allowedMethods = [
        'addresses' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'addressLine1', 'addressLine2', 'addressLine3', 'locality', 'organization', 'fullName', 'fixedOrder'],
        'assets' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'volume', 'kind', 'filename', 'site', 'siteId', 'fixedOrder'],
        'entries' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'slug', 'uri', 'section', 'sectionId', 'postDate', 'site', 'siteId', 'level', 'type', 'relatedTo', 'notRelatedTo', 'andRelatedTo', 'andNotRelatedTo', 'fixedOrder'],
        'users' => ['limit', 'id', 'status', 'offset', 'orderBy', 'search', 'admin', 'group', 'groupId', 'email', 'fullName', 'hasPhoto', 'fixedOrder'],
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
     * @throws BadRequestHttpException|ForbiddenHttpException
     */
    public function executeQuery(string $elementType, array $params, QueryApiSchema $schema): array
    {
        // Throw 400 if elementType is not defined
        if (!isset($this->elementTypeMap[$elementType])) {
            throw new BadRequestHttpException("No matching element type found for type: " . $elementType);
        }

        $this->schema = $schema;
        // Throw 403 if schema does not allow elementType
        Permissions::canQueryElement($elementType, $this->schema);

        $query = $this->buildElementQuery($elementType, $params);
        $queryOne = isset($params['one']) && $params['one'] === '1';
        $queriedDataArr = $queryOne ? [$query->one()] : $query->all();

        // Don't perform permission checks if schema has access to all (*:read) elements or is an empty arr.
        if (!Permissions::canQueryAllElement($elementType, $this->schema)) {
            foreach ($queriedDataArr as $queriedData) {
                if ($queriedData) {
                    $this->validateDataPermission($queriedData, $elementType);
                }
            }
        }

        return $queriedDataArr;
    }

    /**
     *
     * @throws ForbiddenHttpException
     * @throws Exception
     */
    private function buildElementQuery(string $elementType, array $params)
    {
        $allowedMethods = $this->getAllowedMethods($elementType);
        $query = $this->elementTypeMap[$elementType]::find();

        // makes sure that only entries with a section (not nested entries) can get queried by default
        if ($elementType === 'entries') {
            $query->section('*');
        }

        // if id exists, return elements in the same order as the ids are (can be overwritten with the fixedOrder param)
        if (key_exists('id', $params)) {
            $query->fixedOrder(true);
        }

        foreach ($params as $key => $value) {
            if (!in_array($key, $allowedMethods)) {
                continue;
            }

            $processedValue = match ($key) {
                'relatedTo', 'notRelatedTo', 'andRelatedTo', 'andNotRelatedTo' => $this->buildRelatedToQuery($value),
                default => str_contains($value, ',') ? explode(',', $value) : $value,
            };

            $query->$key($processedValue);
        }

        $eagerloadingMap = $this->getEagerLoadingMap();
        $query->with($eagerloadingMap);

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
            if ($keys = $this->getEagerLoadingMapForField($field)) {
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

    private function buildRelatedToQuery($value)
    {
        $jsonDecodedValue = json_decode($value, true);

        // $value = 12
        if (is_numeric($jsonDecodedValue)) {
            return Entry::find()->id($jsonDecodedValue)->one();
        }

        // $value = [1]
        // $value = ['and', 1, 2]
        // $value = [1, ['tE' => 1, 'f' => 'handle']]
        // $value = ['and', ['sE' => 1, 'sS' => 1], ['e' => 1, 'f' => 'handle'], 1]
        if (is_array($jsonDecodedValue)) {
            return $this->processRelatedToArray($jsonDecodedValue);
        }

        return null;
    }

    private function processRelatedToArray(array $array): array
    {
        $result = [];
        $keyMap = [
            'tE' => 'targetElement',
            'sE' => 'sourceElement',
            'e' => 'element',
            'f' => 'field',
            'sS' => 'sourceSite',
        ];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->processRelatedToArray($value);
            } elseif (is_string($key)) {
                $mappedKey = $keyMap[$key] ?? $key;

                if (in_array($mappedKey, ['targetElement', 'sourceElement', 'element']) && is_numeric($value)) {
                    $result[$mappedKey] = Entry::find()->id($value)->one();
                } else {
                    $result[$mappedKey] = $value;
                }
            } elseif (is_numeric($value)) {
                $result[$key] = Entry::find()->id($value)->one();
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function getEagerLoadingMapForField(FieldInterface $field, ?string $prefix = null, int $iteration = 0): array
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
                    $nestedKeys = $this->getEagerLoadingMapForField($subField, $prefix . $field->handle . '.' . $entryType->handle . ':', $iteration);

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
                $this->customElementTypes[$customType->elementTypeHandle] = $customType;

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

    /**
     * @throws ForbiddenHttpException
     */
    private function validateDataPermission($data, $elementType): void
    {
        switch ($elementType) {
            case 'addresses':
                if (!$this->schema->has("addresses.*:read")) {
                    throw new ForbiddenHttpException("Schema doesn't have access to addresses");
                }
                break;

            case 'assets':
                $volume = $data->getVolume();
                if (!$this->schema->has("volumes.{$volume->uid}:read")) {
                    throw new ForbiddenHttpException("Schema doesn't have access to volume with handle: {$volume->handle}");
                }
                break;

            case 'entries':
                $site = $data->getSite();

                if (
                    $site &&
                    !$this->schema->has("sites.{$site->uid}:read") &&
                    !$this->schema->has("sites.*:read")
                ) {
                    throw new ForbiddenHttpException("Schema doesn't have access to site with handle: {$site->handle}");
                }

                $section = $data->getSection();
                if ($section && !$this->schema->has("sections.{$section->uid}:read")) {
                    throw new ForbiddenHttpException("Schema doesn't have access to section with handle: {$section->handle}");
                }
                break;

            case 'users':
                $userGroups = $data->getGroups();

                if ($data->admin) {
                    $userGroups[] = (object)[
                        'uid' => 'admin',
                    ];
                }
                $hasAccess = collect($userGroups)->contains(function($group) {
                    return $this->schema->has("usergroups.{$group->uid}:read");
                });

                if (!$hasAccess) {
                    $groupHandles = implode(', ', array_map(fn($g) => $g->handle ?? 'admin', $userGroups));

                    throw new ForbiddenHttpException("Schema doesn't have access to one of the user groups: {$groupHandles}");
                }
                break;

        }
    }
}
