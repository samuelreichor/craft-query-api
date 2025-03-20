<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;
use craft\validators\UniqueValidator;
use samuelreichoer\queryapi\records\SchemaRecord;

/**
 * Query Api Schema model
 */
class QueryApiSchema extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Schema name
     */
    public ?string $name = null;

    /**
     * @var array The schema’s scope
     */
    public array $scope = [];

    /**
     * @var string|null $uid
     */
    public ?string $uid = null;

    /**
     * Return whether this schema can perform a query
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return is_array($this->scope) && in_array($name, $this->scope, true);
    }
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name'], 'required'];
        $rules[] = [
            ['name'],
            UniqueValidator::class,
            'targetClass' => SchemaRecord::class,
        ];

        return $rules;
    }

    /**
     * Returns the schema’s config.
     *
     * @return array
     * @since 3.5.0
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
        ];

        if ($this->scope) {
            $config['scope'] = $this->scope;
        }

        return $config;
    }
}
