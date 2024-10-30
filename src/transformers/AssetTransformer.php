<?php

namespace samuelreichoer\queryapi\transformers;

use Craft;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use samuelreichoer\queryapi\helpers\Utils;
use yii\base\InvalidConfigException;

class AssetTransformer extends BaseTransformer
{
    private Asset $asset;

    public function __construct(Asset $asset)
    {
        parent::__construct($asset);
        $this->asset = $asset;
    }

    /**
     * @param array $predefinedFields
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    public function getTransformedData(array $predefinedFields = []): array
    {
        $imageMode = Utils::isPluginInstalledAndEnabled('imager-x') ? 'imagerX' : 'craft';

        if ($imageMode === 'imagerX') {
            return $this->imagerXTransformer();
        }

        return $this->defaultImageTransformer();
    }

    /**
     * @return array
     * @throws InvalidConfigException
     * @throws ImageTransformException
     * @throws InvalidFieldException
     */
    private function defaultImageTransformer(): array
    {
        $transformedFields = $this->getTransformedFields();
        return array_merge([
        'metadata' => $this->getMetaData(),
        'height' => $this->asset->height,
        'width' => $this->asset->width,
        'focalPoint' => $this->asset->getFocalPoint(),
        'url' => $this->asset->getUrl(),
    ], $transformedFields);
    }

    /**
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    private function imagerXTransformer(): array
    {
        $data = $this->defaultImageTransformer();
        $data['srcSets'] = $this->getAllAvailableSrcSets();

        return $data;
    }

    /**
     * @return array
     * @throws ImageTransformException
     */
    protected function getMetaData(): array
    {
        return [
        'id' => $this->asset->id,
        'filename' => $this->asset->filename,
        'kind' => $this->asset->kind,
        'size' => $this->asset->size,
        'mimeType' => $this->asset->getMimeType(),
        'extension' => $this->asset->extension,
        'cpEditUrl' => $this->asset->cpEditUrl,
        'dateCreated' => $this->asset->dateCreated,
        'dateUpdated' => $this->asset->dateUpdated,
    ];
    }

    /**
     * @return array
     */
    private function getAllAvailableSrcSets(): array
    {
        $transforms = $this->getTransformKeys();
        $imagerx = Craft::$app->plugins->getPlugin('imager-x');
        $srcSetArr = [];

        foreach ($transforms as $transform) {
            $imagerClass = $imagerx->imager ?? null;
            if ($imagerClass) {
                $transformedImages = $imagerClass->transformImage($this->asset, $transform);
                $srcSetArr[$transform] = $imagerClass->srcset($transformedImages);
            }
        }

        return $srcSetArr;
    }

    /**
     * @return array
     */
    private function getTransformKeys(): array
    {
        $configPath = Craft::getAlias('@config/imager-x-transforms.php');

        if (!file_exists($configPath)) {
            return [];
        }

        $transforms = include $configPath;

        return $transforms ? array_keys($transforms) : [];
    }
}
