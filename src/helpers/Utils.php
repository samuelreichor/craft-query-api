<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;

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
    $plugin = Craft::$app->plugins->getPlugin($pluginHandle, false);

    if ($plugin !== null && $plugin->isInstalled) return true;

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
