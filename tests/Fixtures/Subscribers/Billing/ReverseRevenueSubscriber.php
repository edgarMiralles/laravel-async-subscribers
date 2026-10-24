<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers\Billing;

use EdgarMiralles\AsyncSubscribers\Attributes\OnQueue;
use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderRefunded;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\QueueName;

#[OnQueue(QueueName::Billing)]
final readonly class ReverseRevenueSubscriber implements AsyncSubscriber
{
    public function __construct(private HandledEvents $handledEvents) {}

    public function handle(OrderRefunded $event): void
    {
        $this->handledEvents->record(self::class, $event);
    }
}
