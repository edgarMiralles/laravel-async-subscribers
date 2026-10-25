<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Exceptions\InvalidSubscriber;
use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionFinder;
use EdgarMiralles\AsyncSubscribers\SubscriptionResolver;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;
use Orders\Application\NotifyWarehouseSubscriber;
use Shipping\Application\BookCourierSubscriber;

/**
 * @param  array<array-key, string>  $directories
 * @return list<class-string>
 */
function subscribersFoundIn(array $directories): array
{
    $subscriptions = (new SubscriptionFinder(new SubscriptionResolver, $directories))->find();

    return array_map(fn (Subscription $subscription): string => $subscription->subscriber, $subscriptions);
}

it('finds only concrete AsyncSubscriber classes in *Subscriber.php files, ordered by path', function () {
    $found = subscribersFoundIn([__DIR__ . '/Fixtures/Subscribers']);

    expect($found)->toBe([
        RecordRevenueSubscriber::class,
        ReverseRevenueSubscriber::class,
        SendReceiptSubscriber::class,
    ]);
});

it('reads each class from its file, so one directory can hold several root namespaces', function () {
    $found = subscribersFoundIn([__DIR__ . '/Fixtures/Contexts']);

    expect($found)->toBe([NotifyWarehouseSubscriber::class, BookCourierSubscriber::class]);
});

it('searches several directories in the order they are listed', function () {
    $found = subscribersFoundIn([__DIR__ . '/Fixtures/Contexts', __DIR__ . '/Fixtures/Subscribers/Billing']);

    expect($found)->toBe([
        NotifyWarehouseSubscriber::class,
        BookCourierSubscriber::class,
        RecordRevenueSubscriber::class,
        ReverseRevenueSubscriber::class,
    ]);
});

it('still accepts the older namespace => directory form and ignores the namespace', function () {
    $found = subscribersFoundIn(['Anything\\' => __DIR__ . '/Fixtures/Subscribers/Billing']);

    expect($found)->toBe([RecordRevenueSubscriber::class, ReverseRevenueSubscriber::class]);
});

it('skips a configured directory that does not exist', function () {
    $found = subscribersFoundIn([__DIR__ . '/Fixtures/Missing']);

    expect($found)->toBe([]);
});

it('rejects a discovered subscriber whose handle() takes no event', function () {
    $find = fn (): array => subscribersFoundIn([__DIR__ . '/Fixtures/BrokenSubscribers']);

    expect($find)->toThrow(InvalidSubscriber::class);
});
