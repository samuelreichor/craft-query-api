<?php

namespace samuelreichoer\queryapi;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\utilities\ClearCaches;
use craft\web\UrlManager;
use samuelreichoer\queryapi\models\Settings;
use samuelreichoer\queryapi\services\CacheService;
use samuelreichoer\queryapi\services\ElementQueryService;
use samuelreichoer\queryapi\services\SchemaService;
use samuelreichoer\queryapi\services\TokenService;
use samuelreichoer\queryapi\services\TypescriptService;
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
 * @property CacheService $cache
 * @property ElementQueryService $query
 * @property TypescriptService $typescript
 */
class QueryApi extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;

    /**
     * @var ?QueryApi
     */
    public static ?QueryApi $plugin;
    private bool|null|Settings $_settings;

    public static function config(): array
    {
        return [
            'components' => [
                'schema' => new SchemaService(),
                'token' => new TokenService(),
                'cache' => new CacheService(),
                'query' => new ElementQueryService(),
                'typescript' => new TypescriptService(),
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_initLogger();
        $this->_registerConfigListeners();
        $this->_registerClearCaches();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpRoutes();
            $this->_registerCpTwigExtensions();
            $this->_registerPermissions();
        }

        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            $this->_registerSiteRoutes();
        }

        if ($this->getSettings()->typeGenerationMode === $this->getSettings()::TYPESCRIPT_GENERATION_AUTO) {
            $this->_registerAutoTsGeneration();
        }
    }

    /**
     * @throws Throwable
     */
    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $subNavs = [];
        $isAllowedAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($currentUser->can(Constants::EDIT_SCHEMAS) && $isAllowedAdminChanges) {
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

        if (count($subNavs) <= 1) {
            return array_merge($item, [
                'subnav' => [],
            ]);
        }

        return array_merge($item, [
            'subnav' => $subNavs,
        ]);
    }

    protected function createSettingsModel(): ?Settings
    {
        return new Settings();
    }

    public function getSettings(): ?Settings
    {
        if (!isset($this->_settings)) {
            $this->_settings = $this->createSettingsModel() ?: false;
        }

        return $this->_settings ?: null;
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
                    '/<version>/api/queryApi/customQuery' => 'query-api/default/get-custom-query-result',
                    '/<version>/api/queryApi/allRoutes' => 'query-api/default/get-all-routes',
                    '/<version>/api/queryApi/allRoutes/<siteIds>' => 'query-api/default/get-all-routes',
                ]);
            });
    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $urlRules = [];

            $isAllowedAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
            $currentUser = Craft::$app->getUser()->getIdentity();

            // Cp request but no valid sessionId.
            if (!$currentUser) {
                return;
            }

            $canEditSchemas = $currentUser->can(Constants::EDIT_SCHEMAS) && $isAllowedAdminChanges;
            $canEditTokens = $currentUser->can(Constants::EDIT_TOKENS);

            if ($canEditSchemas) {
                $urlRules['query-api'] = ['template' => 'query-api/schemas/_index.twig'];
                $urlRules['query-api/schemas'] = ['template' => 'query-api/schemas/_index.twig'];
                $urlRules['query-api/schemas/new'] = 'query-api/schema/edit-schema';
                $urlRules['query-api/schemas/<schemaId:\d+>'] = 'query-api/schema/edit-schema';
            }

            if ($canEditTokens) {
                $urlRules['query-api/tokens'] = ['template' => 'query-api/tokens/_index.twig'];
                $urlRules['query-api/tokens/new'] = 'query-api/token/edit-token';
                $urlRules['query-api/tokens/<tokenId:\d+>'] = 'query-api/token/edit-token';

                if (!$canEditSchemas) {
                    $urlRules['query-api'] = ['template' => 'query-api/tokens/_index.twig'];
                }
            }

            $event->rules = array_merge($event->rules, $urlRules);
        });
    }

    private function _registerClearCaches(): void
    {
        Event::on(ClearCaches::class, ClearCaches::EVENT_REGISTER_CACHE_OPTIONS, function(RegisterCacheOptionsEvent $event) {
            $event->options[] = [
                'key' => 'query-api',
                'label' => Craft::t('query-api', 'Query API data cache'),
                'action' => [self::$plugin->cache, 'invalidateCaches'],
            ];
        });
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

    private function _registerAutoTsGeneration(): void
    {
        Event::on(ProjectConfig::class, ProjectConfig::EVENT_AFTER_WRITE_YAML_FILES, function() {
            $outputPath = QueryApi::getInstance()->getSettings()->typeGenerationOutputPath;
            if (!$outputPath) {
                Craft::error("Failed to generate TypeScript definition, output path is empty.", 'queryApi');
                return;
            }
            $this->typescript->generateTsFile($outputPath);
        });
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
