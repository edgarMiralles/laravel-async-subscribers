<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;
use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderRefunded;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderShipped;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\ReverseRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

it('queues one job per subscriber of the event, carrying the event as an array', function () {
    Queue::fake([RunSubscriber::class]);

    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    Queue::assertCount(2);
    Queue::assertPushedOn('emails', RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === SendReceiptSubscriber::class
        && $job->event === OrderPlaced::class
        && $job->payload === ['order_id' => '42', 'email' => 'buyer@shop.test']);
    Queue::assertPushed(RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === RecordRevenueSubscriber::class
        && $job->queue === null
        && $job->event === OrderPlaced::class
        && $job->payload === ['order_id' => '42', 'email' => 'buyer@shop.test']);
});

it('sends a subscriber without #[OnQueue] to the configured default queue, and keeps #[OnQueue] over it', function () {
    Queue::fake([RunSubscriber::class]);
    config(['async-subscribers.queue' => 'general']);

    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    Queue::assertPushedOn('general', RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === RecordRevenueSubscriber::class);
    Queue::assertPushedOn('emails', RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === SendReceiptSubscriber::class);
});

it('queues on the configured connection with the configured tries and backoff', function () {
    Queue::fake([RunSubscriber::class]);
    config([
        'async-subscribers.connection' => 'redis',
        'async-subscribers.tries' => 5,
        'async-subscribers.backoff' => [10, 60],
    ]);

    app(EventBus::class)->dispatch(new OrderRefunded('42', 1999));

    Queue::assertPushed(RunSubscriber::class, fn (RunSubscriber $job): bool => $job->connection === 'redis'
        && $job->tries === 5
        && $job->backoff === [10, 60]);
});

it('queues nothing for an event no subscriber handles', function () {
    Queue::fake();

    app(EventBus::class)->dispatch(new OrderShipped('42'));

    Queue::assertNothingPushed();
});

it('queues the subscribers of every event dispatched together', function () {
    Queue::fake([RunSubscriber::class]);

    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'), new OrderRefunded('42', 1999));

    Queue::assertCount(3);
    Queue::assertPushedOn('billing', RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === ReverseRevenueSubscriber::class
        && $job->event === OrderRefunded::class
        && $job->payload === ['order_id' => '42', 'amount_in_cents' => 1999]);
});

it('holds the jobs until the surrounding transaction commits', function () {
    $this->loadLaravelMigrations();
    config(['queue.default' => 'database']);

    DB::transaction(function (): void {
        app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

        $this->assertDatabaseCount('jobs', 0);
    });

    $this->assertDatabaseCount('jobs', 2);
});

it('drops the jobs when the surrounding transaction rolls back', function () {
    $this->loadLaravelMigrations();
    config(['queue.default' => 'database']);

    $placeOrder = fn () => DB::transaction(function (): void {
        app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

        throw new RuntimeException('Payment declined.');
    });

    expect($placeOrder)->toThrow(RuntimeException::class, 'Payment declined.');
    $this->assertDatabaseCount('jobs', 0);
});

it('queues inside the transaction when after_commit is off', function () {
    $this->loadLaravelMigrations();
    config(['queue.default' => 'database', 'async-subscribers.after_commit' => false]);

    DB::transaction(function (): void {
        app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

        $this->assertDatabaseCount('jobs', 2);
    });
});

it('still queues the other subscribers when one job cannot be queued, then rethrows that failure', function () {
    $queued = [];
    $this->mock(Dispatcher::class, function (MockInterface $dispatcher) use (&$queued): void {
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(function (RunSubscriber $job) use (&$queued): void {
            if ($job->subscriber === RecordRevenueSubscriber::class) {
                throw new RuntimeException('Queue [ledger] does not exist.');
            }

            $queued[] = $job->subscriber;
        });
    });

    $dispatch = fn () => app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    expect($dispatch)->toThrow(RuntimeException::class, 'Queue [ledger] does not exist.');
    expect($queued)->toBe([SendReceiptSubscriber::class]);
});

it('reports each failure after the first instead of losing it', function () {
    Exceptions::fake();
    config(['async-subscribers.connection' => 'missing']);

    $dispatch = fn () => app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    expect($dispatch)->toThrow(InvalidArgumentException::class, 'The [missing] queue connection has not been configured.');
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => $exception->getMessage() === 'The [missing] queue connection has not been configured.');
});
