<?php

namespace samuelreichoer\queryapi\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * Defines the cache duration. Defaults to the cache duration defined in your general.php.
     */
    public ?int $cacheDuration = null;

    /**
     * Define field classes that should be excluded from the response.
     */
    public array $excludeFieldClasses = [];

    public bool $includeFullEntry = false;
}
