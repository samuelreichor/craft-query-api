<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;
use craft\helpers\StringHelper;
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
     * @var array Instance cache for the extracted scope pairs
     */
    private array $_cachedPairs = [];


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
     * Return all scope pairs.
     *
     * @return array
     */
    public function getAllScopePairs(): array
    {
        if (!empty($this->_cachedPairs)) {
            return $this->_cachedPairs;
        }
        foreach ((array)$this->scope as $permission) {
            if (preg_match('/:([\w-]+)$/', $permission, $matches)) {
                $action = $matches[1];
                $permission = StringHelper::removeRight($permission, ':' . $action);
                $parts = explode('.', $permission);
                if (count($parts) === 2) {
                    $this->_cachedPairs[$action][$parts[0]][] = $parts[1];
                } elseif (count($parts) === 1) {
                    $this->_cachedPairs[$action][$parts[0]] = true;
                }
            }
        }
        return $this->_cachedPairs;
    }

    /**
     * Return all scope pairs.
     *
     * @param string $action
     * @return array
     */
    public function getAllScopePairsForAction(string $action = 'read'): array
    {
        $pairs = $this->getAllScopePairs();
        return $pairs[$action] ?? [];
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
