<?php

namespace samuelreichoer\queryapi\jobs;

use Craft;
use craft\elements\Asset;
use craft\queue\BaseJob;
use samuelreichoer\queryapi\helpers\AssetHelper;

class GenerateAssetTransformsJob extends BaseJob
{
    public int $assetId;

    /**
     * @var string[] Transform handles to generate
     */
    public array $transformHandles = [];

    public function execute($queue): void
    {
        $asset = Asset::find()->id($this->assetId)->one();

        if (!$asset instanceof Asset) {
            Craft::warning("Asset with ID {$this->assetId} not found. Can't generate transforms.", 'queryApi');
            return;
        }

        if ($asset->kind !== 'image') {
            return;
        }

        $totalSteps = count($this->transformHandles);

        foreach ($this->transformHandles as $i => $transformHandle) {
            $this->setProgress($queue, $i / $totalSteps, "Generating transform: {$transformHandle}");

            $sizes = AssetHelper::getSrcsetByTransformHandle($transformHandle);

            if (empty($sizes)) {
                Craft::warning("No sizes defined for transform with handle {$transformHandle}. Can't generate transforms.", 'queryApi');
                continue;
            }

            $asset->getSrcset($sizes, $transformHandle);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('query-api', 'Generating image transforms');
    }
}
