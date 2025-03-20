<?php

namespace samuelreichoer\queryapi\migrations;

use Craft;
use craft\db\Migration;
use samuelreichoer\queryapi\Constants;
use yii\base\Exception;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public string $driver;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_SCHEMAS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_SCHEMAS,
                [
                    'id' => $this->primaryKey(),
                    'name' => $this->string(),
                    'scope' => $this->json(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Constants::TABLE_TOKENS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Constants::TABLE_TOKENS,
                [
                    'id' => $this->primaryKey(),
                    'name' => $this->string(),
                    'token' => $this->string(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    protected function removeTables(): void
    {
        $this->dropTableIfExists(Constants::TABLE_SCHEMAS);
        $this->dropTableIfExists(Constants::TABLE_TOKENS);
    }

    protected function addForeignKeys(): void
    {
        /*       $this->addForeignKey(
                   null,
                   Constants::TABLE_SCHEMAS,
                   'id',
                   '{{%elements}}',
                   'id',
                   'CASCADE',
                   null
               );*/
    }
}
