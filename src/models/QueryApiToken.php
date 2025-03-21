<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;
use craft\validators\UniqueValidator;
use DateTime;
use samuelreichoer\queryapi\QueryApi;
use samuelreichoer\queryapi\records\TokenRecord;

/**
 * Query Api Token model
 *
 */
class QueryApiToken extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Token name
     */
    public ?string $name = null;

    /**
     * @var int|null ID of the selected schema.
     */
    public ?int $schemaId = null;

    /**
     * @var string The access token
     */
    public string $accessToken;

    /**
     * @var bool Is the token enabled
     */
    public bool $enabled = true;

    /**
     * @var DateTime|null Date created
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null Date created
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var string|null $uid
     */
    public ?string $uid = null;

    /**
     * @var array|null The allowed scope for the token.
     */
    private ?array $_scope = null;

    /**
     * @var QueryApiSchema|null The schema for this token.
     */
    private ?QueryApiSchema $_schema = null;

    public function __construct($config = [])
    {
        // If the scope is passed in, intercept it and use it.
        if (!empty($config['schema'])) {
            $this->_schema = $config['schema'];

            // We don't want any confusion here, so unset the schema ID, if they set a custom scope.
            unset($config['schemaId']);
        }

        unset($config['schema']);
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['name', 'accessToken'], 'required'];
        $rules[] = [
            ['name', 'accessToken'],
            UniqueValidator::class,
            'targetClass' => TokenRecord::class,
        ];

        return $rules;
    }

    /**
     * Use the translated group name as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Returns whether the token is enabled and has a schema assigned to it.
     *
     * @return bool
     */
    public function getIsValid(): bool
    {
        return $this->enabled && $this->getSchema() !== null;
    }

    /**
     * Return the schema for this token.
     *
     * @return QueryApiSchema|null
     */
    public function getSchema(): ?QueryApiSchema
    {
        if (empty($this->_schema) && !empty($this->schemaId)) {
            $this->_schema = QueryApi::getInstance()->schema->getSchemaById($this->schemaId);
        }

        return $this->_schema;
    }

    /**
     * Sets the schema for this token.
     *
     * @param QueryApiSchema $schema
     */
    public function setSchema(QueryApiSchema $schema): void
    {
        $this->_schema = $schema;
        $this->schemaId = $schema->id;
    }

    /**
     * Return the schema's scope for this token.
     *
     * @return array|null
     */
    public function getScope(): ?array
    {
        if (!isset($this->_scope)) {
            $schema = $this->getSchema();
            $this->_scope = $schema->scope ?? null;
        }

        return $this->_scope;
    }
}
