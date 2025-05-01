<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;
use craft\fieldlayoutelements\users\PhotoField;

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
        return (property_exists($field, 'maxEntries') && $field->maxEntries == 1)
            || (property_exists($field, 'maxRelations') && $field->maxRelations == 1)
            || (property_exists($field, 'maxAddresses') && $field->maxAddresses == 1)
            || (get_class($field) === PhotoField::class);
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
