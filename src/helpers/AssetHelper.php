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
}
