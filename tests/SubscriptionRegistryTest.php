<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\SubscriptionRegistry;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('reads the subscribers from the cache instead of discovering them', function () {
    app(SubscriptionCache::class)->write([new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails')]);

    $subscriptions = app(SubscriptionRegistry::class)->all();

    expect($subscriptions)->toEqual([new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails')]);
});

it('discovers the subscribers when nothing is cached', function () {
    $subscriptions = app(SubscriptionRegistry::class)->all();

    expect(array_map(fn (Subscription $subscription): string => $subscription->subscriber, $subscriptions))->toBe([
        RecordRevenueSubscriber::class,
        ReverseRevenueSubscriber::class,
        SendReceiptSubscriber::class,
    ]);
});
