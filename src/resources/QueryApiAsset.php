<?php

namespace samuelreichoer\queryapi\resources;

use craft\web\AssetBundle;

/**
 * Query Api Bundle asset bundle
 */
class QueryApiAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/src';
    public $js = [
        'script.js',
    ];
    public $css = [
        'style.css',
    ];
}
