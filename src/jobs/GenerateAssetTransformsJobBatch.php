<?php

namespace samuelreichoer\queryapi\jobs;

use Craft;
use craft\base\Batchable;
use craft\db\QueryBatcher;
use craft\elements\Asset;
use craft\queue\BaseBatchedJob;
use samuelreichoer\queryapi\helpers\AssetHelper;

class GenerateAssetTransformsJobBatch extends BaseBatchedJob
{
    /**
     * @var string[] Transform handles to generate
     */
    public array $transformHandles = [];

    /**
     * @var string[]|null Volume handles to filter assets by (null = all volumes)
     */
    public ?array $volumeHandles = null;

    protected function loadData(): Batchable
    {
        $query = Asset::find()
            ->kind('image')
            ->orderBy(['elements.id' => SORT_ASC]);

        if ($this->volumeHandles !== null) {
            $query->volume($this->volumeHandles);
        }

        return new QueryBatcher($query);
    }

    protected function processItem(mixed $item): void
    {
        foreach ($this->transformHandles as $transformHandle) {
            $sizes = AssetHelper::getSrcsetByTransformHandle($transformHandle);

            if (empty($sizes)) {
                Craft::warning("No sizes defined for transform with handle {$transformHandle}. Can't generate transforms.", 'queryApi');
                continue;
            }

            /** @var Asset $item */
            $item->getSrcset($sizes, $transformHandle);
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('query-api', 'Generating image transforms');
    }
}
