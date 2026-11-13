<?php

namespace samuelreichoer\queryapi\jobs;

use Craft;
use craft\queue\BaseJob;
use samuelreichoer\queryapi\QueryApi;

/**
 * Takes all cache tags collected since it was scheduled and hands them to the webhooks.
 */
class DispatchCacheWebhooksJob extends BaseJob
{
    public function execute($queue): void
    {
        $webhooks = QueryApi::getInstance()->webhooks;
        $webhooks->dispatch($webhooks->takePendingTags());
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('query-api', 'Dispatching cache invalidation webhooks');
    }
}
