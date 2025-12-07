<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;
use samuelreichoer\queryapi\enums\AssetMode;
use samuelreichoer\queryapi\QueryApi;

class AssetHelper
{
    public static function getAssetMode(): AssetMode
    {
        return Utils::isPluginInstalledAndEnabled('imager-x') ? AssetMode::IMAGERX : AssetMode::CRAFT;
    }

    /**
     * @return array
     */
    public static function getImagerXTransformKeys(): array
    {
        $configPath = Craft::getAlias('@config/imager-x-transforms.php');

        if (!file_exists($configPath)) {
            return [];
        }

        $transforms = include $configPath;

        return $transforms ? array_keys($transforms) : [];
    }

    public static function getCraftTransformKeys(): array
    {
        $transforms = QueryApi::getInstance()->getSettings()->assetTransforms ?? [];
        return array_keys($transforms);
    }

    public static function getCraftTransforms(): array
    {
        return QueryApi::getInstance()->getSettings()->assetTransforms ?? [];
    }

    /**
     * Get srcset sizes for a specific transform handle.
     *
     * @param string $transformHandle
     * @return array
     */
    public static function getSrcsetByTransformHandle(string $transformHandle): array
    {
        $transforms = self::getCraftTransforms();
        return $transforms[$transformHandle]['srcset'] ?? [];
    }

    /**
     * Get transforms that should be generated on save for a specific volume handle.
     *
     * @param string $volumeHandle
     * @return array Transform handles that should be generated for this volume
     */
    public static function getTransformsForVolume(string $volumeHandle): array
    {
        $transforms = self::getCraftTransforms();
        $applicableTransforms = [];

        foreach ($transforms as $transformHandle => $config) {
            $generateOnSaveVolumes = $config['generateOnSaveVolumes'] ?? false;

            // true = generate for all volumes
            if ($generateOnSaveVolumes === true) {
                $applicableTransforms[] = $transformHandle;
                continue;
            }

            // array = generate only for specific volumes
            if (is_array($generateOnSaveVolumes) && in_array($volumeHandle, $generateOnSaveVolumes, true)) {
                $applicableTransforms[] = $transformHandle;
            }
        }

        return $applicableTransforms;
    }
}
