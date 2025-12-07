<?php

namespace samuelreichoer\queryapi\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Queue as QueueHelper;
use samuelreichoer\queryapi\helpers\AssetHelper;
use samuelreichoer\queryapi\jobs\GenerateAssetTransformsJobBatch;
use yii\console\ExitCode;

class ImageTransformsController extends Controller
{
    private string $ALL_FLAG = '*';
    public string $transforms = '*';
    public string $volumes = '*';

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'transforms';
        $options[] = 'volumes';

        return $options;
    }

    public function actionGenerate(): int
    {
        $transformHandles = $this->getTransformHandles();

        if (empty($transformHandles)) {
            $this->stderr("No transforms found. Please define transforms in your query-api.php config.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $volumeHandles = $this->getVolumeHandles();

        $this->stdout("Transforms to generate: " . implode(', ', $transformHandles) . "\n");
        $this->stdout("Volumes: " . implode(', ', $volumeHandles ?? ['all']) . "\n");

        QueueHelper::push(new GenerateAssetTransformsJobBatch([
            'transformHandles' => $transformHandles,
            'volumeHandles' => $volumeHandles,
        ]));

        $this->stdout("Successfully pushed asset generation to queue.\n");
        return ExitCode::OK;
    }

    private function getTransformHandles(): array
    {
        if ($this->transforms === $this->ALL_FLAG) {
            return AssetHelper::getCraftTransformKeys();
        }

        $requestedTransforms = array_map('trim', explode(',', $this->transforms));
        $availableTransforms = AssetHelper::getCraftTransformKeys();

        return array_values(array_intersect($requestedTransforms, $availableTransforms));
    }

    private function getVolumeHandles(): ?array
    {
        if ($this->volumes === $this->ALL_FLAG) {
            return null;
        }

        $requestedVolumes = array_map('trim', explode(',', $this->volumes));
        $volumeHandles = [];

        /* Make sure to only generate transforms for valid volumes. */
        foreach ($requestedVolumes as $volumeHandle) {
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);
            if ($volume) {
                $volumeHandles[] = $volume->handle;
            } else {
                $this->stderr("Warning: Volume with handle '{$volumeHandle}' not found.\n");
            }
        }

        return !empty($volumeHandles) ? $volumeHandles : null;
    }
}
