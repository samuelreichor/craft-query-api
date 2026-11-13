<?php

namespace samuelreichoer\queryapi\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Queue as QueueHelper;
use samuelreichoer\queryapi\Constants;
use samuelreichoer\queryapi\jobs\DispatchCacheWebhooksJob;
use samuelreichoer\queryapi\jobs\SendCacheWebhookJob;
use samuelreichoer\queryapi\QueryApi;
use yii\base\Exception;

/**
 * Notifies external endpoints (e.g. a headless frontend) whenever Query API caches get invalidated.
 *
 * Invalidated cache tags are collected in the cache. The first invalidation schedules a delayed
 * DispatchCacheWebhooksJob, which takes all tags collected until then and sends them to every webhook.
 */
class WebhookService extends Component
{
    /**
     * Tags that mean "everything is invalid".
     */
    private const GLOBAL_TAGS = ['element', Constants::CACHE_TAG_GlOBAL];

    private const PENDING_TAGS_KEY = 'queryapi:webhooks:pending-tags';
    private const JOB_SCHEDULED_KEY = 'queryapi:webhooks:job-scheduled';
    private const LOCK_NAME = 'queryapi:webhooks';

    /**
     * Seconds to wait before sending, so changes in quick succession are sent together.
     */
    private const DISPATCH_DELAY = 5;

    /**
     * Returns the configured webhooks with resolved environment variables.
     *
     * @return array<int, array{url: string, secret: ?string, headers: array<string, string>}>
     */
    public function getWebhooks(): array
    {
        $webhooks = [];

        foreach (QueryApi::getInstance()->getSettings()->webhooks as $webhook) {
            $url = App::parseEnv($webhook['url'] ?? null);

            if (!$url) {
                continue;
            }

            $headers = [];
            foreach ($webhook['headers'] ?? [] as $name => $value) {
                $headers[$name] = App::parseEnv($value);
            }

            $webhooks[] = [
                'url' => $url,
                'secret' => App::parseEnv($webhook['secret'] ?? null) ?: null,
                'headers' => $headers,
            ];
        }

        return $webhooks;
    }

    public function hasWebhooks(): bool
    {
        return !empty($this->getWebhooks());
    }

    /**
     * Handles tags coming from Craft's element cache invalidation.
     * Changes to drafts and revisions are ignored, as they never reach the API.
     *
     * @param string[] $tags
     */
    public function handleInvalidatedElementTags(array $tags): void
    {
        foreach ($tags as $tag) {
            if (str_ends_with($tag, '::drafts') || str_ends_with($tag, '::revisions')) {
                return;
            }
        }

        $this->queueTags($tags);
    }

    /**
     * Adds tags to the pending list and schedules a dispatch job, if none is waiting yet.
     * All tags collected until the job runs are sent together.
     *
     * @param string[] $tags
     */
    public function queueTags(array $tags): void
    {
        if (empty($tags) || !$this->hasWebhooks()) {
            return;
        }

        $this->_withLock(function() use ($tags) {
            $cache = Craft::$app->getCache();

            $pending = $cache->get(self::PENDING_TAGS_KEY) ?: [];
            foreach ($tags as $tag) {
                $pending[$tag] = true;
            }
            $cache->set(self::PENDING_TAGS_KEY, $pending, 0);

            if ($cache->get(self::JOB_SCHEDULED_KEY)) {
                return;
            }

            // Expires on its own, so a lost job can't block webhooks forever.
            $cache->set(self::JOB_SCHEDULED_KEY, true, self::DISPATCH_DELAY + 300);
            QueueHelper::push(new DispatchCacheWebhooksJob(), null, self::DISPATCH_DELAY);
        });
    }

    /**
     * Returns all pending tags and clears the list.
     *
     * @return string[]
     */
    public function takePendingTags(): array
    {
        return $this->_withLock(function() {
            $cache = Craft::$app->getCache();
            $tags = array_keys($cache->get(self::PENDING_TAGS_KEY) ?: []);
            $cache->delete(self::PENDING_TAGS_KEY);
            $cache->delete(self::JOB_SCHEDULED_KEY);

            return $tags;
        });
    }

    /**
     * Pushes one send job per webhook, so a failing endpoint is retried on its own.
     *
     * @param string[] $tags
     */
    public function dispatch(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $payload = [
            'event' => 'invalidate',
            'all' => !empty(array_intersect($tags, self::GLOBAL_TAGS)),
            'tags' => $tags,
            'timestamp' => time(),
        ];

        foreach ($this->getWebhooks() as $webhook) {
            // Only the URL is stored in the queue, the secret is resolved when the job runs.
            QueueHelper::push(new SendCacheWebhookJob([
                'url' => $webhook['url'],
                'payload' => $payload,
            ]));
        }
    }

    private function _withLock(callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire(self::LOCK_NAME, 5)) {
            throw new Exception('Could not acquire the Query API webhook lock.');
        }

        try {
            return $callback();
        } finally {
            $mutex->release(self::LOCK_NAME);
        }
    }

    /**
     * Returns the webhook config for the given URL.
     *
     * @return array{url: string, secret: ?string, headers: array<string, string>}|null
     */
    public function getWebhookByUrl(string $url): ?array
    {
        foreach ($this->getWebhooks() as $webhook) {
            if ($webhook['url'] === $url) {
                return $webhook;
            }
        }

        return null;
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }
}
