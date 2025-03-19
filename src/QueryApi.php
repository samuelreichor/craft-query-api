<?php

namespace samuelreichoer\queryapi;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use samuelreichoer\queryapi\models\Settings;
use samuelreichoer\queryapi\twigextensions\SchemaHelper;
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
    public bool $hasCpSection = true;

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

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerCpTwigExtensions();
        }

    }

    private function attachEventHandlers(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES,
        function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
              'GET /<version>/api/queryApi/customQuery' => 'query-api/default/get-custom-query-result',
              'GET /<version>/api/queryApi/allRoutes' => 'query-api/default/get-all-routes',
              'GET /<version>/api/queryApi/allRoutes/<siteId>' => 'query-api/default/get-all-routes',
          ]);
        });
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'schemas' =>  [
                'label' => 'Schemas',
                'url' => 'query-api/schemas'
            ],
            'tokens' => [
                'label' => 'Tokens',
                'url' => 'query-api/tokens',
            ]
        ];
        return $item;
    }



    protected function createSettingsModel(): ?Model
    {
        return new Settings();
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

    private function _registerPermissions(): void
    {

    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'query-api' => ['template' => 'query-api/schemas/index.twig'],
                'query-api/schemas/new' => ['template' => 'query-api/schemas/edit.twig'],
                'query-api/schemas/edit/<schemaId:\d+>' => ['template' => 'query-api/schemas/edit.twig'],
                'query-api/tokens/new' => ['template' => 'query-api/tokens/edit.twig'],
                'query-api/tokens/edit/<tokenId:\d+>' => ['template' => 'query-api/tokens/edit.twig'],
            ]);
        });
    }

    private function _registerClearCaches(): void
    {

    }

    private function _registerCpTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new SchemaHelper());
    }
}
