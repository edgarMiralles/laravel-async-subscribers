<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Jobs;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;
use EdgarMiralles\AsyncSubscribers\SubscriberJobLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Date;

final class RunSubscriber implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  class-string<AsyncSubscriber>  $subscriber
     * @param  class-string<SerializableEvent>  $event
     * @param  array<string, mixed>  $payload
     * @param  int|list<int>  $backoff
     */
    public function __construct(
        public readonly string $subscriber,
        public readonly string $event,
        public readonly array $payload,
        public int $tries,
        public int | array $backoff,
    ) {}

    public function handle(Container $container, SubscriberJobLogger $logger): void
    {
        $logger->consuming($this);
        $startedAt = Date::now();

        $container->make($this->subscriber)->handle($this->event::fromArray($this->payload));

        $logger->consumed($this, $startedAt->diffInMilliseconds(Date::now()));
    }

    public function displayName(): string
    {
        return $this->subscriber;
    }

    /** @return list<string> */
    public function tags(): array
    {
        return [$this->subscriber, $this->event];
    }
}
