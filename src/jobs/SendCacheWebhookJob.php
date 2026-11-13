<?php

namespace samuelreichoer\queryapi\jobs;

use Craft;
use craft\helpers\Json;
use craft\queue\BaseJob;
use samuelreichoer\queryapi\QueryApi;
use samuelreichoer\queryapi\services\WebhookService;
use yii\queue\RetryableJobInterface;

class SendCacheWebhookJob extends BaseJob implements RetryableJobInterface
{
    private const MAX_ATTEMPTS = 5;

    public string $url;

    public array $payload = [];

    public function execute($queue): void
    {
        $webhook = QueryApi::getInstance()->webhooks->getWebhookByUrl($this->url);

        if ($webhook === null) {
            Craft::warning("Webhook {$this->url} is no longer configured. Skipping.", 'queryApi');
            return;
        }

        $body = Json::encode($this->payload);
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'Craft-Query-API',
        ], $webhook['headers']);

        if ($webhook['secret']) {
            $headers['X-Query-Api-Signature'] = WebhookService::sign($body, $webhook['secret']);
        }

        // Throws on connection errors and 4xx/5xx responses, so the queue retries the job.
        Craft::createGuzzleClient()->post($this->url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 10,
        ]);
    }

    public function getTtr(): int
    {
        return 30;
    }

    public function canRetry($attempt, $error): bool
    {
        return $attempt < self::MAX_ATTEMPTS;
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('query-api', 'Sending cache invalidation webhook');
    }
}
