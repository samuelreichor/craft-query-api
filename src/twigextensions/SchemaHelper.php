<?php

namespace samuelreichoer\queryapi\twigextensions;

use craft\helpers\UrlHelper;
use samuelreichoer\queryapi\QueryApi;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SchemaHelper extends AbstractExtension
{
    public function getName(): string
    {
        return 'Returns all schema components';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getSchemaComponents', $this->getSchemaComponents(...)),
            new TwigFunction('getAllSchemas', $this->getAllSchemas(...)),
        ];
    }

    /**
     * Returns all schema components
     */
    public function getSchemaComponents(): array
    {
        return QueryApi::getInstance()->schema->getSchemaComponents();
    }

    /**
     * Returns all schemas
     */
    public function getAllSchemas(): array
    {
        $schemas = QueryApi::getInstance()->schema->getSchemas();

        return array_map(function($schema) {
            return [
                'id' => $schema->id,
                'title' => $schema->name,
                'url' => UrlHelper::url('query-api/schemas/' . $schema->id),
            ];
        }, $schemas);
    }
}
