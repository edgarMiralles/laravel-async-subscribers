<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Console;

use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\SubscriptionFinder;
use Illuminate\Console\Command;

final class CacheSubscriptionsCommand extends Command
{
    protected $signature = 'async-subscribers:cache';

    protected $description = 'Discover the async subscribers and cache them for production';

    public function handle(SubscriptionFinder $finder, SubscriptionCache $cache): int
    {
        $subscriptions = $finder->find();

        $cache->write($subscriptions);

        $this->components->info(sprintf('Async subscribers cached successfully. [%d total]', count($subscriptions)));

        return self::SUCCESS;
    }
}
