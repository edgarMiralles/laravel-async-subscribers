<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;
use EdgarMiralles\AsyncSubscribers\Exceptions\InvalidSubscriber;
use EdgarMiralles\AsyncSubscribers\Subscription;
use EdgarMiralles\AsyncSubscribers\SubscriptionResolver;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderRefunded;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

final class ResolverOptionalLocaleSubscriber implements AsyncSubscriber
{
    public function handle(OrderPlaced $event, string $locale = 'en'): void {}
}

final class ResolverWithoutHandleSubscriber implements AsyncSubscriber {}

final class ResolverPrivateHandleSubscriber implements AsyncSubscriber
{
    private function handle(OrderPlaced $event): void {}
}

final class ResolverNoParameterSubscriber implements AsyncSubscriber
{
    public function handle(): void {}
}

final class ResolverUntypedSubscriber implements AsyncSubscriber
{
    public function handle($event): void {}
}

final class ResolverScalarSubscriber implements AsyncSubscriber
{
    public function handle(string $event): void {}
}

final class ResolverNonEventSubscriber implements AsyncSubscriber
{
    public function handle(stdClass $event): void {}
}

final class ResolverEventInterfaceSubscriber implements AsyncSubscriber
{
    public function handle(SerializableEvent $event): void {}
}

final class ResolverUnionSubscriber implements AsyncSubscriber
{
    public function handle(OrderPlaced | OrderRefunded $event): void {}
}

final class ResolverRequiredLocaleSubscriber implements AsyncSubscriber
{
    public function handle(OrderPlaced $event, string $locale): void {}
}

it('reads the event from the handle() type and the queue from #[OnQueue]', function (string $subscriber, Subscription $expected) {
    $subscription = (new SubscriptionResolver)->resolve($subscriber);

    expect($subscription)->toEqual($expected);
})->with([
    'string queue' => [
        SendReceiptSubscriber::class,
        new Subscription(SendReceiptSubscriber::class, OrderPlaced::class, 'emails'),
    ],
    'backed enum queue' => [
        ReverseRevenueSubscriber::class,
        new Subscription(ReverseRevenueSubscriber::class, OrderRefunded::class, 'billing'),
    ],
    'no #[OnQueue]' => [
        RecordRevenueSubscriber::class,
        new Subscription(RecordRevenueSubscriber::class, OrderPlaced::class, null),
    ],
]);

it('accepts optional parameters after the event', function () {
    $subscription = (new SubscriptionResolver)->resolve(ResolverOptionalLocaleSubscriber::class);

    expect($subscription->event)->toBe(OrderPlaced::class);
});

it('rejects a subscriber without a public handle() method', function (string $subscriber) {
    $resolve = fn (): Subscription => (new SubscriptionResolver)->resolve($subscriber);

    expect($resolve)->toThrow(InvalidSubscriber::class, "{$subscriber} must declare a public handle() method.");
})->with([
    'no handle()' => ResolverWithoutHandleSubscriber::class,
    'private handle()' => ResolverPrivateHandleSubscriber::class,
]);

it('rejects a handle() that does not take exactly one event', function (string $subscriber) {
    $resolve = fn (): Subscription => (new SubscriptionResolver)->resolve($subscriber);

    expect($resolve)->toThrow(
        InvalidSubscriber::class,
        "{$subscriber}::handle() must take exactly one parameter, type-hinted with a class that implements "
            . 'EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent.',
    );
})->with([
    'no parameter' => ResolverNoParameterSubscriber::class,
    'untyped parameter' => ResolverUntypedSubscriber::class,
    'scalar type' => ResolverScalarSubscriber::class,
    'class that is not an event' => ResolverNonEventSubscriber::class,
    'the SerializableEvent interface itself' => ResolverEventInterfaceSubscriber::class,
    'union of events' => ResolverUnionSubscriber::class,
    'second required parameter' => ResolverRequiredLocaleSubscriber::class,
]);
