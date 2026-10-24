<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionCache;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderRefunded;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('lists each subscriber with its event and queue, showing the default queue in brackets', function (?string $defaultQueue, string $shownDefault) {
    config(['async-subscribers.queue' => $defaultQueue]);

    $this->artisan('async-subscribers:list')
        ->expectsTable(['Event', 'Subscriber', 'Queue'], [
            [OrderPlaced::class, RecordRevenueSubscriber::class, $shownDefault],
            [OrderRefunded::class, ReverseRevenueSubscriber::class, 'billing'],
            [OrderPlaced::class, SendReceiptSubscriber::class, 'emails'],
        ])
        ->doesntExpectOutputToContain('Listed from the cache')
        ->assertSuccessful();
})->with([
    'no default queue' => [null, '(connection default)'],
    'a default queue' => ['general', '(general)'],
]);

it('warns that the list comes from the cache when one exists', function () {
    app(SubscriptionCache::class)->write([new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails')]);

    $this->artisan('async-subscribers:list')
        ->expectsTable(['Event', 'Subscriber', 'Queue'], [
            [OrderPlaced::class, SendReceiptSubscriber::class, 'emails'],
        ])
        ->expectsOutputToContain('Listed from the cache. Run async-subscribers:clear to see subscribers added since.')
        ->assertSuccessful();
});

it('says so when no subscriber is found', function () {
    config(['async-subscribers.discover' => []]);

    $this->artisan('async-subscribers:list')
        ->expectsOutputToContain('No async subscribers found.')
        ->assertSuccessful();
});
