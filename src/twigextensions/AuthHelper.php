<?php

namespace samuelreichoer\queryapi\twigextensions;

use craft\helpers\UrlHelper;
use samuelreichoer\queryapi\QueryApi;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AuthHelper extends AbstractExtension
{
    public function getName(): string
    {
        return 'Helper to get data in schema and token index pages';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getSchemaComponents', $this->getSchemaComponents(...)),
            new TwigFunction('getAllSchemas', $this->getAllSchemas(...)),
            new TwigFunction('getAllTokens', $this->getAllTokens(...)),
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

    public function getAllTokens(): array
    {
        $tokens = QueryApi::getInstance()->token->getTokens();

        return array_map(function($schema) {
            return [
                'id' => $schema->id,
                'title' => $schema->name,
                'status' => $schema->enabled,
                'url' => UrlHelper::url('query-api/tokens/' . $schema->id),
            ];
        }, $tokens);
    }
}
