<?php

namespace samuelreichoer\queryapi\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use DateTime;
use DateTimeInterface;
use yii\console\ExitCode;

/**
 * Cache related commands.
 */
class CacheController extends Controller
{
    private const LAST_RUN_CACHE_KEY = 'queryapi:scheduled-invalidation:last-run';

    /**
     * @var int Fallback window in seconds if the command never ran before (or the cache was cleared).
     */
    public int $lookback = 3600;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'lookback';

        return $options;
    }

    /**
     * Invalidates caches for entries that went live (postDate) or expired (expiryDate)
     * since the last run. Meant to run via cron, e.g. every minute.
     */
    public function actionInvalidateScheduled(): int
    {
        $now = new DateTime();
        $cache = Craft::$app->getCache();
        $lastRun = $cache->get(self::LAST_RUN_CACHE_KEY);
        $since = $lastRun ? (new DateTime())->setTimestamp((int)$lastRun) : (new DateTime())->modify("-{$this->lookback} seconds");

        $range = ['and', '>' . $since->format(DateTimeInterface::ATOM), '<=' . $now->format(DateTimeInterface::ATOM)];

        $baseQuery = Entry::find()
            ->site('*')
            ->unique()
            ->status(null);

        /** @var Entry[] $entries */
        $entries = [];
        foreach ([(clone $baseQuery)->postDate($range), (clone $baseQuery)->expiryDate($range)] as $query) {
            foreach ($query->all() as $entry) {
                $entries[$entry->id] = $entry;
            }
        }

        $elements = Craft::$app->getElements();
        foreach ($entries as $entry) {
            // Invalidates Craft/Query API caches and triggers the cache webhooks.
            $elements->invalidateCachesForElement($entry);
        }

        $cache->set(self::LAST_RUN_CACHE_KEY, $now->getTimestamp(), 0);

        $this->stdout('Invalidated caches for ' . count($entries) . " scheduled entries.\n");
        return ExitCode::OK;
    }
}
