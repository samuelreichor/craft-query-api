<?php

namespace samuelreichoer\queryapi;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use yii\log\FileTarget;

/**
 * CraftQuery API plugin
 *
 * @method static QueryApi getInstance()
 * @author Samuel Reichoer <samuelreichor@gmail.com>
 * @copyright Samuel Reichoer
 * @license MIT
 */
class QueryApi extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
        'components' => [
          // Define component configs here...
        ],
    ];
    }

    public function init(): void
    {
        parent::init();

        $this->initLogger();
        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES,
        function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
              'GET /<version>/api/queryApi/customQuery' => 'craft-query-api/default/get-custom-query-result',
              'GET /<version>/api/queryApi/allRoutes' => 'craft-query-api/default/get-all-routes',
              'GET /<version>/api/queryApi/allRoutes/<siteId>' => 'craft-query-api/default/get-all-routes',
          ]);
        }
    );
    }

    private function initLogger(): void
    {
        $logFileTarget = new FileTarget([
        'logFile' => '@storage/logs/queryApi.log',
        'maxLogFiles' => 10,
        'categories' => ['queryApi'],
        'logVars' => [],
    ]);
        Craft::getLogger()->dispatcher->targets[] = $logFileTarget;
    }
}
