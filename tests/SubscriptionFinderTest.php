<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Exceptions\InvalidSubscriber;
use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionFinder;
use EdgarMiralles\AsyncSubscribers\SubscriptionResolver;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

/**
 * @param  array<string, string>  $directories
 * @return list<class-string>
 */
function subscribersFoundIn(array $directories): array
{
    $subscriptions = (new SubscriptionFinder(new SubscriptionResolver, $directories))->find();

    return array_map(fn (Subscription $subscription): string => $subscription->subscriber, $subscriptions);
}

it('finds only concrete AsyncSubscriber classes in *Subscriber.php files, ordered by path', function () {
    $found = subscribersFoundIn([
        'EdgarMiralles\\AsyncSubscribers\\Tests\\Fixtures\\Subscribers\\' => __DIR__ . '/Fixtures/Subscribers',
    ]);

    expect($found)->toBe([
        RecordRevenueSubscriber::class,
        ReverseRevenueSubscriber::class,
        SendReceiptSubscriber::class,
    ]);
});

it('maps a namespace prefix onto its directory with or without the trailing backslash', function (string $namespace) {
    $found = subscribersFoundIn([$namespace => __DIR__ . '/Fixtures/Subscribers/Billing']);

    expect($found)->toBe([RecordRevenueSubscriber::class, ReverseRevenueSubscriber::class]);
})->with([
    'with backslash' => 'EdgarMiralles\\AsyncSubscribers\\Tests\\Fixtures\\Subscribers\\Billing\\',
    'without backslash' => 'EdgarMiralles\\AsyncSubscribers\\Tests\\Fixtures\\Subscribers\\Billing',
]);

it('skips a configured directory that does not exist', function () {
    $found = subscribersFoundIn(['App\\Missing\\' => __DIR__ . '/Fixtures/Missing']);

    expect($found)->toBe([]);
});

it('rejects a discovered subscriber whose handle() takes no event', function () {
    $find = fn (): array => subscribersFoundIn([
        'EdgarMiralles\\AsyncSubscribers\\Tests\\Fixtures\\BrokenSubscribers\\' => __DIR__ . '/Fixtures/BrokenSubscribers',
    ]);

    expect($find)->toThrow(InvalidSubscriber::class);
});
