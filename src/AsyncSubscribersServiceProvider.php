<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Console\CacheSubscriptionsCommand;
use EdgarMiralles\AsyncSubscribers\Console\ClearSubscriptionsCommand;
use EdgarMiralles\AsyncSubscribers\Console\ListSubscriptionsCommand;
use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class AsyncSubscribersServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../config/async-subscribers.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'async-subscribers');

        $this->app->singleton(SubscriptionFinder::class, fn (Application $app): SubscriptionFinder => new SubscriptionFinder(
            $app->make(SubscriptionResolver::class),
            $app->make('config')->get('async-subscribers.discover', []),
        ));

        $this->app->singleton(SubscriptionCache::class, fn (Application $app): SubscriptionCache => new SubscriptionCache(
            $app->bootstrapPath('cache/async-subscribers.php'),
        ));

        $this->app->singleton(SubscriptionRegistry::class);
        $this->app->bind(EventBus::class, QueueEventBus::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([self::CONFIG => config_path('async-subscribers.php')], 'async-subscribers-config');

        $this->commands([
            CacheSubscriptionsCommand::class,
            ClearSubscriptionsCommand::class,
            ListSubscriptionsCommand::class,
        ]);

        $this->optimizes(optimize: 'async-subscribers:cache', clear: 'async-subscribers:clear');
    }
}
