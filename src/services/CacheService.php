<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\QueryApi;
use yii\caching\TagDependency;

class CacheService extends Component
{
    public function invalidateCaches(): void
    {
        $cache = Craft::$app->getCache();
        TagDependency::invalidate($cache, Constants::CACHE_TAG_GlOBAL);
        Craft::info(
            'All query API caches cleared',
            __METHOD__
        );
    }

    public function getCacheDuration(): int
    {
        return QueryApi::getInstance()->getSettings()->cacheDuration
            ?? Craft::$app->getConfig()->getGeneral()->cacheDuration;
    }
}
