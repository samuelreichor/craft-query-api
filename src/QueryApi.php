<?php

namespace samuelreichoer\queryapi;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use samuelreichoer\queryapi\models\Settings;
use samuelreichoer\queryapi\services\SchemaService;
use samuelreichoer\queryapi\services\TokenService;
use samuelreichoer\queryapi\twigextensions\AuthHelper;
use Throwable;
use yii\base\Event;
use yii\log\FileTarget;

/**
 * CraftQuery API plugin
 *
 * @method static QueryApi getInstance()
 * @author Samuel Reichoer <samuelreichor@gmail.com>
 * @copyright Samuel Reichoer
 * @license MIT
 *
 * @property SchemaService $schema
 * @property TokenService $token
 */
class QueryApi extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'schema' => new SchemaService(),
                'token' => new TokenService(),
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_initLogger();
        $this->_registerConfigListeners();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerCpTwigExtensions();
            $this->_registerPermissions();
        }

        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            $this->_registerSiteRoutes();
        }
    }

    /**
     * @throws Throwable
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $subNavs = [];

        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($currentUser->can(Constants::EDIT_SCHEMAS)) {
            $subNavs['schemas'] = [
                'label' => 'Schemas',
                'url' => 'query-api/schemas',
            ];
        }

        if ($currentUser->can(Constants::EDIT_TOKENS)) {
            $subNavs['tokens'] = [
                'label' => 'Tokens',
                'url' => 'query-api/tokens',
            ];
        }

        if (empty($subNavs)) {
            return null;
        }

        return array_merge($item, [
            'subnav' => $subNavs,
        ]);
    }


    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    private function _initLogger(): void
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
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Query API',
                    'permissions' => [
                        Constants::EDIT_SCHEMAS => [
                            'label' => 'Manage Schemas',
                        ],
                        Constants::EDIT_TOKENS => [
                            'label' => 'Manage Tokens',
                        ],
                    ],
                ];
            }
        );
    }

    private function _registerSiteRoutes(): void
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

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'query-api' => ['template' => 'query-api/schemas/_index.twig'],
                'query-api/schemas' => ['template' => 'query-api/schemas/_index.twig'],
                'query-api/schemas/new' => 'query-api/schema/edit-schema',
                'query-api/schemas/<schemaId:\d+>' => 'query-api/schema/edit-schema',
                'query-api/tokens' => ['template' => 'query-api/tokens/_index.twig'],
                'query-api/tokens/new' => 'query-api/token/edit-token',
                'query-api/tokens/<tokenId:\d+>' => 'query-api/token/edit-token',
            ]);
        });
    }

    private function _registerClearCaches(): void
    {
    }

    private function _registerCpTwigExtensions(): void
    {
        Craft::$app->view->registerTwigExtension(new AuthHelper());
    }

    private function _registerConfigListeners(): void
    {
        Craft::$app->getProjectConfig()
            ->onAdd(Constants::PATH_SCHEMAS . '.{uid}', $this->_proxy('schema', 'handleChangedSchema'))
            ->onUpdate(Constants::PATH_SCHEMAS . '.{uid}', $this->_proxy('schema', 'handleChangedSchema'))
            ->onRemove(Constants::PATH_SCHEMAS . '.{uid}', $this->_proxy('schema', 'handleDeletedSchema'));
    }

    /**
     * Returns a proxy function for calling a component method, based on its ID.
     *
     * The component won’t be fetched until the method is called, avoiding unnecessary component instantiation, and ensuring the correct component
     * is called if it happens to get swapped out (e.g. for a test).
     *
     * @param string $id The component ID
     * @param string $method The method name
     * @return callable
     */
    private function _proxy(string $id, string $method): callable
    {
        return function() use ($id, $method) {
            return $this->get($id)->$method(...func_get_args());
        };
    }
}
