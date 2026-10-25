<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests;

use EdgarMiralles\AsyncSubscribers\AsyncSubscribersServiceProvider;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function setUp(): void
    {
        parent::setUp();

        $cache = $this->app->make(SubscriptionCache::class);
        $cache->clear();
        $this->beforeApplicationDestroyed(fn () => $cache->clear());
    }

    protected function getPackageProviders($app): array
    {
        return [AsyncSubscribersServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('logging.default', 'null');
        $app['config']->set('async-subscribers.discover', [__DIR__ . '/Fixtures/Subscribers']);

        $app->singleton(HandledEvents::class);
    }
}
