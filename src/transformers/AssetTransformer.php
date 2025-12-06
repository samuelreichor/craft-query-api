<?php

namespace samuelreichoer\queryapi\transformers;

use Craft;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\errors\InvalidFieldException;
use samuelreichoer\queryapi\enums\AssetMode;
use samuelreichoer\queryapi\helpers\AssetHelper;
use yii\base\InvalidConfigException;

class AssetTransformer extends BaseTransformer
{
    private Asset $asset;

    public function __construct(Asset $asset, array $predefinedFields = [])
    {
        parent::__construct($asset, $predefinedFields);
        $this->asset = $asset;
    }

    /**
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     * @throws InvalidFieldException
     */
    public function getTransformedData(): array
    {
        $imageMode = AssetHelper::getAssetMode();

        $data = match ($imageMode) {
            AssetMode::IMAGERX => $this->imagerXTransformer(),
            AssetMode::CRAFT => $this->craftTransformer(),
        };

        return $this->smartFilter($data, array_keys($data));
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
            'height' => $this->asset->getHeight(),
            'width' => $this->asset->getWidth(),
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
        $data['srcSets'] = $this->getAllImagerXSrcSets();

        return $data;
    }

    /**
     * @return array
     * @throws ImageTransformException
     * @throws InvalidConfigException
     */
    protected function getMetaData(): array
    {
        return [
            'id' => $this->asset->getId(),
            'filename' => $this->asset->getFilename(),
            'kind' => $this->asset->kind,
            'size' => $this->asset->getFormattedSize(),
            'mimeType' => $this->asset->getMimeType(),
            'extension' => $this->asset->getExtension(),
            'cpEditUrl' => $this->asset->getCpEditUrl(),
            'volumeId' => $this->asset->volume->getId(),
        ];
    }

    /**
     * @return array
     */
    private function getAllImagerXSrcSets(): array
    {
        $transforms = AssetHelper::getImagerXTransformKeys();
        $imagerX = Craft::$app->plugins->getPlugin('imager-x');
        $srcSetArr = [];

        foreach ($transforms as $transform) {
            $imagerClass = $imagerX->imager ?? null;
            if ($imagerClass) {
                $transformedImages = $imagerClass->transformImage($this->asset, $transform);
                $srcSetArr[$transform] = $imagerClass->srcset($transformedImages);
            }
        }

        return $srcSetArr;
    }

    /**
     * @return array
     * @throws InvalidConfigException
     * @throws ImageTransformException
     * @throws InvalidFieldException
     */
    private function craftTransformer(): array
    {
        $data = $this->defaultImageTransformer();
        $srcSets = $this->getAllCraftSrcSets();

        if (!empty($srcSets)) {
            $data['srcSets'] = $srcSets;
        }

        return $data;
    }

    private function getAllCraftSrcSets(): array
    {
        $transforms = AssetHelper::getCraftTransforms();
        $srcSetArr = [];

        foreach ($transforms as $transformHandle => $sizes) {
            if (empty($sizes)) {
                continue;
            }

            $srcSetArr[$transformHandle] = $this->asset->getSrcset($sizes, $transformHandle);
        }

        return $srcSetArr;
    }
}
