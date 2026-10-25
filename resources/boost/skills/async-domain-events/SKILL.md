---
name: async-domain-events
description: "Create domain events and the queued subscribers that react to them with edgarmiralles/laravel-async-subscribers. Use when adding an event that something should react to, a subscriber (a class ending in Subscriber that implements AsyncSubscriber), a side effect that should run on a queue after a state change, a queue choice with #[OnQueue], a dispatch through EventBus, or tests for any of these. Also use when a subscriber never runs, or to trace a job through its enqueued/consuming/consumed logs."
---

# Async Domain Events

An event implements `SerializableEvent`. A subscriber implements `AsyncSubscriber` and is discovered automatically. `EventBus::dispatch()` queues one `RunSubscriber` job per subscriber of that event, so every subscriber runs on a queue worker, never in the request.

## Before you start

- Find the existing events and subscribers, and match their location and naming: run `grep -rl "implements SerializableEvent" app` and `php artisan async-subscribers:list`.
- If the project has none yet, ask the user where events and subscribers belong instead of inventing a layout.
- Read `discover` in `config/async-subscribers.php` (default `app_path()`). It lists the directories searched for subscribers; a subscriber outside them is never found.

## 1. The event

- Name it for what happened, in the past tense: `OrderPlaced`, `TicketRefunded`.
- Make it a `final readonly class` that implements `SerializableEvent`.
- Carry ids and scalars, never models. The subscriber reloads what it needs, so it acts on current data.
- `toArray()` writes every field and `fromArray()` reads every field back. Enums go across as `->value` and come back with `::from()`; dates go across as `format(DATE_ATOM)`.
- That array is what the queue stores, what `failed_jobs` keeps and what a retry reads. Keep it small and leave secrets out.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use App\Domain\Ordering\PaymentMethod;
use DateTimeImmutable;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;

final readonly class OrderPlaced implements SerializableEvent
{
    public function __construct(
        public int $orderId,
        public PaymentMethod $paymentMethod,
        public DateTimeImmutable $placedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'payment_method' => $this->paymentMethod->value,
            'placed_at' => $this->placedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            (int) $data['order_id'],
            PaymentMethod::from($data['payment_method']),
            new DateTimeImmutable($data['placed_at']),
        );
    }
}
```

### Changing an event that is already in production

Jobs already queued, and failed jobs that may be retried, still hold the old array under the old class name.

- To add a field, give it a default in `fromArray()`: `$data['coupon_code'] ?? null`.
- Do not rename or remove a key, or rename or move the class, while old jobs can still run. Add the new shape, deploy, and remove the old one once the queues have drained.

## 2. The subscriber

- Write one subscriber per side effect, and have each one react to one event. Several subscribers can react to the same event.
- The class and file name must end in `Subscriber`, or discovery skips it. Name it for what it does: `SendReceiptSubscriber`.
- Make it `final`, implement `AsyncSubscriber`, and declare one public `handle(TheEvent $event): void`. The type-hint says which event it reacts to. Only that exact class matches: a parent class or interface does not.
- Take dependencies through the constructor. The worker resolves the subscriber from the container.
- Choose its queue with `#[OnQueue('emails')]`, which also accepts a backed enum. Without the attribute, the job goes to `async-subscribers.queue`, or to the connection's default queue if that is not set. Only use queues a worker consumes. On SQS (and Vapor), queuing to a queue that does not exist throws.
- Expect it to run more than once: jobs get 3 tries by default, and a queue can deliver a message twice. Reload the state and skip work that is already done.
- Let exceptions escape. A thrown exception is what makes the job retry and, on its last try, land in `failed_jobs`. Do not catch and log.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Subscribers;

use App\Domain\Ordering\Events\OrderPlaced;
use App\Mail\OrderReceipt;
use App\Models\Order;
use EdgarMiralles\AsyncSubscribers\Attributes\OnQueue;
use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use Illuminate\Support\Facades\Mail;

#[OnQueue('emails')]
final class SendReceiptSubscriber implements AsyncSubscriber
{
    public function handle(OrderPlaced $event): void
    {
        $order = Order::query()->findOrFail($event->orderId);

        if ($order->receipt_sent_at !== null) {
            return;
        }

        Mail::to($order->email)->send(new OrderReceipt($order));

        $order->update(['receipt_sent_at' => now()]);
    }
}
```

## 3. Dispatch

Inject `EventBus` and dispatch right after the state change that the event describes.

```php
use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;

final readonly class PlaceOrderHandler
{
    public function __construct(private EventBus $events) {}

    public function handle(PlaceOrder $command): Order
    {
        return DB::transaction(function () use ($command): Order {
            $order = Order::query()->create($command->attributes());

            $this->events->dispatch(new OrderPlaced($order->id, $command->paymentMethod, new DateTimeImmutable));

            return $order;
        });
    }
}
```

- Dispatching inside a transaction is safe. The jobs wait for the commit and are dropped on rollback (`after_commit`, on by default), so do not wrap the call in `DB::afterCommit()`.
- `dispatch()` takes several events: `$this->events->dispatch(...$events)`.
- An event with no subscribers queues nothing, and that is not an error.
- If one job cannot be queued, the others are still queued, then the first error is thrown.
- Laravel's `event()` helper does not reach these subscribers. Use `EventBus`.

## 4. Check the wiring

```bash
php artisan async-subscribers:list
```

It shows each event, its subscribers and their queue.
- If it warns that it is reading from the cache, a new subscriber will not appear or run until you run `php artisan async-subscribers:clear`.
- In production, `php artisan optimize` (or `async-subscribers:cache`) builds the cache. A subscriber with a malformed `handle()` makes that command fail with `InvalidSubscriber`, so fix it before you deploy.

## 5. Tests

Test each piece on its own.

The event must survive the trip through the queue:

```php
it('rebuilds itself from its array', function () {
    $event = new OrderPlaced(42, PaymentMethod::Card, new DateTimeImmutable('2026-01-02T03:04:05+00:00'));

    expect(OrderPlaced::fromArray($event->toArray()))->toEqual($event);
});
```

Test the subscriber directly. Resolve it from the container and call `handle()`, then assert its side effect. Also test the path where the work is already done:

```php
it('emails the receipt for a placed order', function () {
    Mail::fake();
    $order = Order::factory()->create();

    app(SendReceiptSubscriber::class)->handle(new OrderPlaced($order->id, PaymentMethod::Card, new DateTimeImmutable));

    Mail::assertSent(OrderReceipt::class, fn (OrderReceipt $mail): bool => $mail->hasTo($order->email));
});
```

For the code that dispatches, assert which subscriber was queued, on which queue, and with which data. `RunSubscriber` exposes `subscriber`, `event` (the event's class) and `payload` (the event's array):

```php
use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;

Queue::fake([RunSubscriber::class]);

app(PlaceOrderHandler::class)->handle($command);

Queue::assertPushedOn('emails', RunSubscriber::class, fn (RunSubscriber $job): bool => $job->subscriber === SendReceiptSubscriber::class
    && $job->payload['order_id'] === $order->id);
```

- `Queue::fake()` ignores transactions, so it records jobs even when the transaction rolls back. To test rollback, use the `database` queue connection and assert on the `jobs` table.
- If `Queue::assertPushed` finds nothing, run `async-subscribers:list` to check that the subscriber is discovered.

## Tracing a job in the logs

Each job logs `Async subscriber enqueued`, `Async subscriber consuming` and `Async subscriber consumed`.
- Every line carries `subscriber`, `event`, `job_uuid`, `queue_job_id`, `connection` and `queue`. The consuming and consumed lines add `attempt`, and consumed adds `duration_ms`.
- `job_uuid` is the same on every retry and matches the `uuid` column of `failed_jobs`. Search the logs for it to follow one job.
- "consuming" without "consumed" means the subscriber threw.
- `ASYNC_SUBSCRIBERS_LOG=false` turns the lines off.

Worker output, Horizon, Sentry and Vapor all show the job under the subscriber's class name.

## A subscriber never runs

1. Is it in `async-subscribers:list`? If not, check that:
   - the file name ends in `Subscriber`;
   - it sits under a `discover` directory, declares its namespace, and its class is named like its file;
   - it implements `AsyncSubscriber` and is not abstract;
   - the cache is not stale (`async-subscribers:clear`).
2. Does `handle()` type-hint the exact class being dispatched?
3. Is the event dispatched through `EventBus` rather than `event()`?
4. Did the transaction commit? Dispatches inside a rolled-back transaction are dropped.
5. Is a worker consuming that queue? Look for the `enqueued` line, then for `consuming`.
