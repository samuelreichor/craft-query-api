<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use craft\db\Query as DbQuery;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use Exception;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\models\QueryApiSchema;
use samuelreichoer\queryapi\QueryApi;
use yii\db\Expression;

class SchemaService extends Component
{
    /**
     * Saves a Query API schema.
     *
     * @param QueryApiSchema $schema the schema to save
     * @param bool $runValidation Whether the schema should be validated
     * @return bool Whether the schema was saved successfully
     * @throws Exception
     */
    public function saveSchema(QueryApiSchema $schema, bool $runValidation = true): bool
    {
        $isNewSchema = !$schema->id;

        if ($runValidation && !$schema->validate()) {
            Craft::info('Schema not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewSchema && empty($schema->uid)) {
            $schema->uid = StringHelper::UUID();
        } elseif (empty($schema->uid)) {
            $schema->uid = Db::uidById(Constants::TABLE_SCHEMAS, $schema->id);
        }

        $configPath = Constants::PATH_SCHEMAS . '.' . $schema->uid;
        $configData = $schema->getConfig();
        Craft::$app->getProjectConfig()->set($configPath, $configData, "Save Query API schema “{$schema->name}”");

        if ($isNewSchema) {
            $schema->id = Db::idByUid(Constants::TABLE_SCHEMAS, $schema->uid);
        }

        return true;
    }

    /**
     * Handle schema change
     *
     * @param ConfigEvent $event
     * @throws \yii\db\Exception
     */
    public function handleChangedSchema(ConfigEvent $event): void
    {
        // Get the UID that was matched in the config path
        $uid = $event->tokenMatches[0];

        // Does this schema exist?
        $id = Db::idByUid(Constants::TABLE_SCHEMAS, $uid);
        $isNew = empty($id);

        if ($isNew) {
            Db::insert(Constants::TABLE_SCHEMAS, [
                'name' => $event->newValue['name'],
                'scope' => $event->newValue['scope'] ?? [],
                'dateCreated' => new Expression('NOW()'),
                'dateUpdated' => new Expression('NOW()'),
                'uid' => $uid,
            ]);
        } else {
            Db::update(Constants::TABLE_SCHEMAS, [
                'name' => $event->newValue['name'],
                'scope' => $event->newValue['scope'] ?? [],
                'dateUpdated' => new Expression('NOW()'),
            ], ['id' => $id]);
        }
    }

    /**
     * @throws Exception
     */
    public function handleDeletedSchema(ConfigEvent $event): void
    {
        // Get the UID that was matched in the config path:
        $uid = $event->tokenMatches[0];
        $schema = $this->getSchemaByUid($uid);

        // If that came back empty, we’re done—must have already been deleted!
        if (!$schema) {
            return;
        }

        Db::delete(Constants::TABLE_SCHEMAS, ['id' => $schema->id]);
    }

    /**
     * Deletes a Query API schema by its ID.
     *
     * @param int $id The schema's ID
     * @return bool Whether the schema was deleted.
     */
    public function deleteSchemaById(int $id): bool
    {
        $schema = $this->getSchemaById($id);

        if (!$schema) {
            return false;
        }

        return $this->deleteSchema($schema);
    }

    /**
     * Deletes a Query API schema.
     *
     * @param QueryApiSchema $schema
     * @return bool
     */
    public function deleteSchema(QueryApiSchema $schema): bool
    {
        Craft::$app->getProjectConfig()->remove(Constants::PATH_SCHEMAS . '.' . $schema->uid, "Delete the “{$schema->name}” Query API schema");
        return true;
    }

    /**
     * Get a schema by its ID.
     *
     * @param int $id The schema's ID
     * @return QueryApiSchema|null
     */
    public function getSchemaById(int $id): ?QueryApiSchema
    {
        $result = $this->_createSchemaQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new QueryApiSchema($result) : null;
    }

    /**
     * Get a schema by its UID.
     *
     * @param string $uid The schema's UID
     * @return QueryApiSchema|null
     */
    public function getSchemaByUid(string $uid): ?QueryApiSchema
    {
        $result = $this->_createSchemaQuery()
            ->where(['uid' => $uid])
            ->one();

        return $result ? new QueryApiSchema($result) : null;
    }

    /**
     * Get a schema by its name.
     *
     * @param string $name The schema's name
     * @return QueryApiSchema|null
     */
    public function getSchemaByName(string $name): ?QueryApiSchema
    {
        $result = $this->_createSchemaQuery()
            ->where(['name' => $name])
            ->one();

        return $result ? new QueryApiSchema($result) : null;
    }

    /**
     * Get all schemas.
     *
     * @return QueryApiSchema[]
     */
    public function getSchemas(): array
    {
        $rows = $this->_createSchemaQuery()
            ->all();

        $schemas = [];

        foreach ($rows as $row) {
            $schemas[] = new QueryApiSchema($row);
        }

        return $schemas;
    }

    public function getSchemaComponents(): array
    {
        $queries = [];
        $mutations = [];

        // Sites
        $label = Craft::t('app', 'Sites');
        [$queries[$label], $mutations[$label]] = $this->_siteSchemaComponents();

        // Sections
        $label = Craft::t('app', 'Sections');
        [$queries[$label], $mutations[$label]] = $this->_sectionSchemaComponents();

        // User Groups
        $label = Craft::t('app', 'User Groups');
        [$queries[$label], $mutations[$label]] = $this->_userSchemaComponents();

        // Volumes
        $label = Craft::t('app', 'Volumes');
        [$queries[$label], $mutations[$label]] = $this->_volumeSchemaComponents();

        // Addresses
        $label = Craft::t('app', 'Addresses');
        [$queries[$label], $mutations[$label]] = $this->_sectionSchemaAddresses();

        // Cusom Elements
        list($customQueries, $customMutations) = $this->_sectionSchemaOther();
        if (!empty($customQueries)) {
            $label = Craft::t('app', 'Custom Element Types');
            $queries[$label] = $customQueries;
            $mutations[$label] = $customMutations;
        }

        return [
            'queries' => $queries,
            'mutations' => $mutations,
        ];
    }

    /**
     * Return site schema components.
     *
     * @return array
     */
    private function _siteSchemaComponents(): array
    {
        $sites = Craft::$app->getSites()->getAllSites(true);
        $queryComponents["sites.*:read"] = [
            'label' => Craft::t('app', 'All sites'),
            'class' => 'select-all',
        ];

        foreach ($sites as $site) {
            $queryComponents["sites.{$site->uid}:read"] = [
                'label' => Craft::t('app', 'Query for elements in the “{site}” site', [
                    'site' => $site->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _sectionSchemaComponents(): array
    {
        $sections = Craft::$app->entries->getAllSections();
        $queryComponents["sections.*:read"] = [
            'label' => Craft::t('app', 'All sections'),
            'class' => 'select-all',
        ];

        foreach ($sections as $section) {
            $queryComponents["sections.{$section->uid}:read"] = [
                'label' => Craft::t('app', 'Query for elements in the “{section}” section', [
                    'section' => $section->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _userSchemaComponents(): array
    {
        $userGroups = Craft::$app->userGroups->getAllGroups();
        $queryComponents["usergroups.*:read"] = [
            'label' => Craft::t('app', 'All users'),
            'class' => 'select-all',
        ];

        $queryComponents["usergroups.admin:read"] = [
            'label' => Craft::t('app', 'Query for “Admin” users'),
        ];

        foreach ($userGroups as $userGroup) {
            $queryComponents["usergroups.{$userGroup->uid}:read"] = [
                'label' => Craft::t('app', 'Query for users in the “{usergroup}” user group', [
                    'usergroup' => $userGroup->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _volumeSchemaComponents(): array
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $queryComponents["volumes.*:read"] = [
            'label' => Craft::t('app', 'All volumes'),
            'class' => 'select-all',
        ];

        foreach ($volumes as $volume) {
            $queryComponents["volumes.{$volume->uid}:read"] = [
                'label' => Craft::t('app', 'Query for assets in the “{volume}” volume', [
                    'volume' => $volume->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _sectionSchemaAddresses(): array
    {
        $queryComponents["addresses.*:read"] = [
            'label' => Craft::t('app', 'All addresses'),
            'class' => 'select-all',
        ];

        return [$queryComponents, []];
    }

    private function _sectionSchemaOther(): array
    {
        $customElementTypes = QueryApi::getInstance()->query->getCustomElementTypes();

        $queryComponents = [];
        foreach ($customElementTypes as $customElementType) {
            $queryComponents[$customElementType->elementTypeHandle . ":read"] = [
                'label' => Craft::t('app', 'Query for all elements of type “{handle}”', [
                    'handle' => $customElementType->elementTypeHandle,
                ]),
            ];
        }
        return [$queryComponents, []];
    }

    /**
     * Returns a DbCommand object prepped for retrieving schemas.
     *
     * @return DbQuery
     */
    private function _createSchemaQuery(): DbQuery
    {
        return (new DbQuery())
            ->select([
                'id',
                'name',
                'scope',
                'uid',
            ])
            ->from([Constants::TABLE_SCHEMAS]);
    }
}
