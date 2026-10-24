<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Console;

use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use Illuminate\Console\Command;

final class ClearSubscriptionsCommand extends Command
{
    protected $signature = 'async-subscribers:clear';

    protected $description = 'Clear the cached async subscribers';

    public function handle(SubscriptionCache $cache): int
    {
        $cache->clear();

        $this->components->info('Async subscribers cache cleared successfully.');

        return self::SUCCESS;
    }
}
