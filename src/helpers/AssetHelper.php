<?php

namespace samuelreichoer\queryapi\helpers;

use Craft;
use samuelreichoer\queryapi\enums\AssetMode;

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
}
