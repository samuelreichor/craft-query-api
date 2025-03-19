<?php

namespace samuelreichoer\queryapi\twigextensions;

use samuelreichoer\queryapi\services\SchemaService;
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
            new TwigFunction('getSchemaComponents', [$this, 'getSchemaComponents']),
        ];
    }

    /**
     * Returns all schema components
     */
    public function getSchemaComponents(): array
    {
        $queryService = new SchemaService();
        return $queryService->getSchemaComponents();
    }
}
