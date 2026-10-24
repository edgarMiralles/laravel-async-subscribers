<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('caches the subscribers when the app is optimized', function () {
    $this->artisan('optimize', ['--except' => 'config,events,routes,views'])->assertSuccessful();

    expect(app()->bootstrapPath('cache/async-subscribers.php'))->toBeFile();
});

it('clears the cached subscribers when the optimization is cleared', function () {
    app(SubscriptionCache::class)->write([new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails')]);

    $this->artisan('optimize:clear', ['--except' => 'config,cache,compiled,events,routes,views'])->assertSuccessful();

    expect(app()->bootstrapPath('cache/async-subscribers.php'))->not->toBeFile();
});
