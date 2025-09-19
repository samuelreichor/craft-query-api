<?php

namespace samuelreichoer\queryapi\console\controllers;

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
        if (QueryApi::getInstance()->typescript->generateTsFile($output)) {
            $this->stdout("✔ TypeScript file written to: $output\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
