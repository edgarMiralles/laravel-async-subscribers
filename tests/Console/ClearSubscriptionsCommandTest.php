<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('deletes the cache file', function () {
    app(SubscriptionCache::class)->write([new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails')]);

    $this->artisan('async-subscribers:clear')
        ->expectsOutputToContain('Async subscribers cache cleared successfully.')
        ->assertSuccessful();

    expect(app()->bootstrapPath('cache/async-subscribers.php'))->not->toBeFile();
});
