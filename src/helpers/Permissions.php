<?php

namespace samuelreichoer\queryapi\helpers;

use samuelreichoer\queryapi\models\QueryApiSchema;
use yii\web\ForbiddenHttpException;

class Permissions
{
    /**
     * Extracts all the allowed entities from a schema for the given action.
     *
     * @param string $action The action for which the entities should be extracted. Defaults to "read".
     * @param QueryApiSchema|null $schema The Query API schema.
     * @return array
     */
    public static function extractAllowedEntitiesFromSchema(string $action = 'read', ?QueryApiSchema $schema = null): array
    {
        return $schema->getAllScopePairsForAction($action);
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQuerySites(QueryApiSchema $schema): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        if (!isset($allowedEntities['sites'])) {
            throw new ForbiddenHttpException('Schema doesn’t have access to any site');
        }

        return true;
    }

    public static function canQueryAllSites(QueryApiSchema $schema): bool
    {
        return $schema->has('sites.*:read');
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQueryEntries(QueryApiSchema $schema): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        if (!isset($allowedEntities['sections'])) {
            throw new ForbiddenHttpException('Schema doesn’t have access to any section');
        }

        return true;
    }

    public static function canQueryAllEntries(QueryApiSchema $schema): bool
    {
        return $schema->has('sections.*:read');
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQueryUsers(QueryApiSchema $schema): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        if (!isset($allowedEntities['usergroups'])) {
            throw new ForbiddenHttpException('Schema doesn’t have access to any user group');
        }
        return true;
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQueryAssets(QueryApiSchema $schema): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        if (!isset($allowedEntities['volumes'])) {
            throw new ForbiddenHttpException('Schema doesn’t have access to any volume');
        }
        return true;
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQueryAddresses(QueryApiSchema $schema): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema('read', $schema);

        if (!isset($allowedEntities['addresses'])) {
            throw new ForbiddenHttpException('Schema doesn’t have access to addresses');
        }
        return true;
    }

    /**
     * @throws ForbiddenHttpException
     */
    public static function canQueryElement(string $elementType, QueryApiSchema $schema): void
    {
        $checkPermissions = [
            'assets' => fn($schema) => self::canQueryAssets($schema),
            'entries' => fn($schema) => self::canQueryEntries($schema),
            'users' => fn($schema) => self::canQueryUsers($schema),
            'addresses' => fn($schema) => self::canQueryAddresses($schema),
        ];

        if (isset($checkPermissions[$elementType])) {
            $checkPermissions[$elementType]($schema);
        }
    }

    public static function canQueryAllElement(string $elementType, QueryApiSchema $schema): bool
    {
        $checkPermissions = [
            'assets' => fn($schema) => $schema->has('volumes.*:read'),
            'entries' => fn($schema) => $schema->has('sections.*:read') && $schema->has('sites.*:read'),
            'users' => fn($schema) => $schema->has('usergroups.*:read'),
            'addresses' => fn($schema) => $schema->has('addresses.*:read'),
        ];

        if (isset($checkPermissions[$elementType])) {
            return $checkPermissions[$elementType]($schema);
        }

        return false;
    }
}
