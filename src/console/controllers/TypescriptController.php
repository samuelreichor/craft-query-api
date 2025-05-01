<?php

namespace samuelreichoer\queryapi\console\controllers;

use Craft;
use craft\console\Controller;
use samuelreichoer\queryapi\QueryApi;
use yii\console\ExitCode;
use yii\helpers\Console;

class TypescriptController extends Controller
{
    public ?string $output = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'output';

        return $options;
    }

    public function actionGenerateTypes(): int
    {
        $output = $this->output ?? '@root/queryApiTypes.ts';
        $types = QueryApi::getInstance()->typescript->getTypes();
        $outputPath = Craft::getAlias($output);

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($outputPath, $types);

        $this->stdout("✔ TypeScript file written to: $outputPath\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
