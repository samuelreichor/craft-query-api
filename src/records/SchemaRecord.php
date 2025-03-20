<?php

namespace samuelreichoer\queryapi\records;

use craft\db\ActiveRecord;
use samuelreichoer\queryapi\Constants;

/**
 * Schema record
 * @property int $id ID
 * @property string $name Schema name
 * @property array $scope The scope of the schema
 */
class SchemaRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return Constants::TABLE_SCHEMAS;
    }
}
