<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Console;

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\SubscriptionRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

final class ListSubscriptionsCommand extends Command
{
    protected $signature = 'async-subscribers:list';

    protected $description = 'List each event, the subscribers that react to it, and their queue';

    public function handle(SubscriptionRegistry $registry, SubscriptionCache $cache, Repository $config): int
    {
        $subscriptions = $registry->all();

        if ($subscriptions === []) {
            $this->components->info('No async subscribers found.');

            return self::SUCCESS;
        }

        $defaultQueue = $config->get('async-subscribers.queue') ?? 'connection default';

        $this->table(['Event', 'Subscriber', 'Queue'], array_map(
            fn (Subscription $subscription): array => [
                $subscription->event,
                $subscription->subscriber,
                $subscription->queue ?? "({$defaultQueue})",
            ],
            $subscriptions,
        ));

        if ($cache->exists()) {
            $this->components->warn('Listed from the cache. Run async-subscribers:clear to see subscribers added since.');
        }

        return self::SUCCESS;
    }
}
