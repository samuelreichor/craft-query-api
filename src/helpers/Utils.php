<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;
use craft\fieldlayoutelements\users\PhotoField;
use craft\fields\ButtonGroup;
use craft\fields\Checkboxes;
use craft\fields\Dropdown;
use craft\fields\MultiSelect;
use craft\fields\RadioButtons;
use craft\fields\Table;

class Utils
{
    /**
     * Checks if a plugin is installed
     *
     * @param string $pluginHandle
     * @return bool
     */
    public static function isPluginInstalledAndEnabled(string $pluginHandle): bool
    {
        $plugin = Craft::$app->plugins->getPlugin($pluginHandle);

        if ($plugin !== null && $plugin->isInstalled) {
            return true;
        }

        return false;
    }

    /**
     * Generates a cache key based on the given parameters.
     *
     * @param array $params
     * @return string
     */
    public static function generateCacheKey(array $params): string
    {
        $normalizedParams = self::recursiveKsort($params);
        $serializedParams = json_encode($normalizedParams);
        return hash('sha256', $serializedParams);
    }

    /**
     * Returns the full uri based on a given uri
     *
     * @param string|null $url
     * @return string|null
     */
    public static function getFullUriFromUrl(?string $url): string|null
    {
        if (!$url) {
            return null;
        }

        $pattern = '/https?:\/\/[^\/]+(\/.*)/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return '/';
    }

    public static function isSingleRelationField($field): bool
    {
        return (property_exists($field, 'maxEntries') && $field->maxEntries === 1)
            || (property_exists($field, 'maxRelations') && $field->maxRelations === 1)
            || (property_exists($field, 'maxAddresses') && $field->maxAddresses === 1)
            || (get_class($field) === PhotoField::class);
    }

    public static function isArrayField($field): bool
    {
        return (property_exists($field, 'maxEntries') && $field->maxEntries !== 1)
            || (property_exists($field, 'maxRelations') && $field->maxRelations !== 1)
            || (property_exists($field, 'maxAddresses') && $field->maxAddresses !== 1)
            || (get_class($field) === Checkboxes::class)
            || (get_class($field) === MultiSelect::class)
            || (get_class($field) === Table::class);
    }

    public static function isRequiredField($field): bool
    {
        $c = get_class($field);

        // If option field has default option -> set it to required as there is always a valid value
        if ($c === Dropdown::class || $c === RadioButtons::class || $c === Checkboxes::class || $c === MultiSelect::class || $c === ButtonGroup::class) {
            if (property_exists($field, 'options')) {
                foreach ($field->options as $option) {
                    if ($option["default"] === '1') {
                        return true;
                    }
                }
            }
        }
        return (property_exists($field, 'required') && $field->required);
    }

    /**
     * Recursively sorts an array by its keys.
     *
     * @param array $array
     * @return array
     */
    private static function recursiveKsort(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::recursiveKsort($value);
            }
        }
        ksort($array);
        return $array;
    }
}
