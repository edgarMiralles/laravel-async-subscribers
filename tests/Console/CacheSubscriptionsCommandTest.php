<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderRefunded;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('writes every discovered subscriber, with its event and queue, to bootstrap/cache', function () {
    $this->artisan('async-subscribers:cache')
        ->expectsOutputToContain('Async subscribers cached successfully. [3 total]')
        ->assertSuccessful();

    expect(app()->bootstrapPath('cache/async-subscribers.php'))->toBeFile();
    expect(app(SubscriptionCache::class)->read())->toEqual([
        new Subscription(RecordRevenueSubscriber::class, OrderPlaced::class, null),
        new Subscription(ReverseRevenueSubscriber::class, OrderRefunded::class, 'billing'),
        new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails'),
    ]);
});
