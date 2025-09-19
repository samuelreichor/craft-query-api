<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * @const string
     */
    public const TYPESCRIPT_GENERATION_MANUAL = 'manual';

    /**
     * @const string
     */
    public const TYPESCRIPT_GENERATION_AUTO = 'auto';
    /**
     * Defines the cache duration. Defaults to the cache duration defined in your general.php.
     */
    public ?int $cacheDuration = null;

    /**
     * Define field classes that should be excluded from the response.
     */
    public array $excludeFieldClasses = [];

    /**
     * Defines how entry relations from an Entries field are returned.
     * If enabled, the customQuery endpoint will include full entry objects,
     * otherwise only title, URI, ID, slug is returned.
     */
    public bool $includeFullEntry = false;

    /**
     * Determines how ts types for your frontend should be created.
     *
     * - `self::TYPESCRIPT_GENERATION_MANUAL`: Generate types with the cli manually
     * - `self::TYPESCRIPT_GENERATION_AUTO`: Generate types after every project config write
     */
    public string $typeGenerationMode = self::TYPESCRIPT_GENERATION_MANUAL;

    /**
     * Defines where ts definitions get created. Aliases can be used here as well.
     */
    public string $typeGenerationOutputPath = '@root/queryApiTypes.ts';
}
