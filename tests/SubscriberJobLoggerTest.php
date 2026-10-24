<?php

declare(strict_types=1);

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;
use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderShipped;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing\RecordRevenueSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\SendReceiptSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class LoggerTestOfflineWarehouseSubscriber implements AsyncSubscriber
{
    public function handle(OrderShipped $event): void
    {
        throw new RuntimeException('Warehouse is offline.');
    }
}

final readonly class LoggerTestFlakyCarrierSubscriber implements AsyncSubscriber
{
    public function __construct(private HandledEvents $handledEvents) {}

    public function handle(OrderShipped $event): void
    {
        $this->handledEvents->record(self::class, $event);

        if (count($this->handledEvents->handled) === 1) {
            throw new RuntimeException('Carrier API timed out.');
        }
    }
}

final class LoggerTestUnrelatedJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}

/** @return Collection<int, array{message: string, context: array<string, mixed>}> */
function recordSubscriberJobLogs(): Collection
{
    $logs = new Collection;

    Event::listen(MessageLogged::class, function (MessageLogged $log) use ($logs): void {
        if (str_starts_with($log->message, 'Async subscriber')) {
            $logs->push(['message' => $log->message, 'context' => $log->context]);
        }
    });

    return $logs;
}

function queueSubscriberJobsInDatabase(): void
{
    test()->loadLaravelMigrations();
    config(['queue.default' => 'database']);
    Str::createUuidsUsingSequence([
        '00000000-0000-0000-0000-00000000000a',
        '00000000-0000-0000-0000-00000000000b',
    ]);
}

afterEach(function () {
    Str::createUuidsNormally();
});

it('logs each job once it is on the queue, tagged with its uuid, queue id and queue', function () {
    queueSubscriberJobsInDatabase();
    $logs = recordSubscriberJobLogs();

    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));

    expect($logs->all())->toBe([
        ['message' => 'Async subscriber enqueued', 'context' => [
            'subscriber' => RecordRevenueSubscriber::class,
            'event' => OrderPlaced::class,
            'job_uuid' => '00000000-0000-0000-0000-00000000000a',
            'queue_job_id' => 1,
            'connection' => 'database',
            'queue' => 'default',
        ]],
        ['message' => 'Async subscriber enqueued', 'context' => [
            'subscriber' => SendReceiptSubscriber::class,
            'event' => OrderPlaced::class,
            'job_uuid' => '00000000-0000-0000-0000-00000000000b',
            'queue_job_id' => 2,
            'connection' => 'database',
            'queue' => 'emails',
        ]],
    ]);
});

it('logs consuming and consumed around each subscriber, with the uuid it was enqueued under', function () {
    queueSubscriberJobsInDatabase();
    $this->freezeTime();
    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));
    $logs = recordSubscriberJobLogs();

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--queue' => 'emails,default'])->assertSuccessful();

    $sendReceipt = [
        'subscriber' => SendReceiptSubscriber::class,
        'event' => OrderPlaced::class,
        'job_uuid' => '00000000-0000-0000-0000-00000000000b',
        'queue_job_id' => 2,
        'connection' => 'database',
        'queue' => 'emails',
        'attempt' => 1,
    ];
    $recordRevenue = [
        'subscriber' => RecordRevenueSubscriber::class,
        'event' => OrderPlaced::class,
        'job_uuid' => '00000000-0000-0000-0000-00000000000a',
        'queue_job_id' => 1,
        'connection' => 'database',
        'queue' => 'default',
        'attempt' => 1,
    ];
    expect($logs->all())->toBe([
        ['message' => 'Async subscriber consuming', 'context' => $sendReceipt],
        ['message' => 'Async subscriber consumed', 'context' => [...$sendReceipt, 'duration_ms' => 0.0]],
        ['message' => 'Async subscriber consuming', 'context' => $recordRevenue],
        ['message' => 'Async subscriber consumed', 'context' => [...$recordRevenue, 'duration_ms' => 0.0]],
    ]);
});

it('keeps the uuid and counts the attempt when a failed job is retried', function () {
    queueSubscriberJobsInDatabase();
    dispatch(new RunSubscriber(LoggerTestFlakyCarrierSubscriber::class, OrderShipped::class, ['order_id' => '42'], 2, 0));
    $logs = recordSubscriberJobLogs();

    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();

    expect($logs->map(fn (array $log): array => [$log['message'], $log['context']['job_uuid'], $log['context']['attempt']])->all())->toBe([
        ['Async subscriber consuming', '00000000-0000-0000-0000-00000000000a', 1],
        ['Async subscriber consuming', '00000000-0000-0000-0000-00000000000a', 2],
        ['Async subscriber consumed', '00000000-0000-0000-0000-00000000000a', 2],
    ]);
});

it('logs consuming but not consumed when the subscriber throws', function () {
    $logs = recordSubscriberJobLogs();
    $job = new RunSubscriber(LoggerTestOfflineWarehouseSubscriber::class, OrderShipped::class, ['order_id' => '42'], 3, 5);

    $run = fn () => app()->call([$job, 'handle']);

    expect($run)->toThrow(RuntimeException::class, 'Warehouse is offline.');
    expect($logs->pluck('message')->all())->toBe(['Async subscriber consuming']);
});

it('does not log jobs that are not subscriber jobs', function () {
    queueSubscriberJobsInDatabase();
    $logs = recordSubscriberJobLogs();

    dispatch(new LoggerTestUnrelatedJob);

    $this->assertDatabaseCount('jobs', 1);
    expect($logs->all())->toBe([]);
});

it('writes none of the lines when logging is off', function () {
    queueSubscriberJobsInDatabase();
    config(['async-subscribers.log' => false]);
    $logs = recordSubscriberJobLogs();

    app(EventBus::class)->dispatch(new OrderPlaced('42', 'buyer@shop.test'));
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--queue' => 'emails,default'])->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    expect($logs->all())->toBe([]);
});
