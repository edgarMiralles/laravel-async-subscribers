<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;
use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;

it('rebuilds the event and hands it to the subscriber, resolved with its dependencies', function () {
    $job = new RunSubscriber(SendReceiptSubscriber::class, OrderPlaced::class, ['order_id' => '42', 'email' => 'buyer@shop.test'], 3, 5);

    app()->call([$job, 'handle']);

    expect(app(HandledEvents::class)->handled)->toEqual([
        ['subscriber' => SendReceiptSubscriber::class, 'event' => new OrderPlaced('42', 'buyer@shop.test')],
    ]);
});

it('runs each subscriber off a real queue and names it after the subscriber in the worker output', function () {
    $this->loadLaravelMigrations();
    config(['queue.default' => 'database']);
    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--queue' => 'emails,default'])
        ->expectsOutputToContain(SendReceiptSubscriber::class)
        ->expectsOutputToContain(RecordRevenueSubscriber::class)
        ->assertSuccessful();

    expect(app(HandledEvents::class)->handled)->toEqual([
        ['subscriber' => SendReceiptSubscriber::class, 'event' => new OrderPlaced('42', 'buyer@shop.test')],
        ['subscriber' => RecordRevenueSubscriber::class, 'event' => new OrderPlaced('42', 'buyer@shop.test')],
    ]);
});
