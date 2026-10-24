<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers;

use EdgarMiralles\AsyncSubscribers\Attributes\OnQueue;
use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;

#[OnQueue('emails')]
final readonly class SendReceiptSubscriber implements AsyncSubscriber
{
    public function __construct(private HandledEvents $handledEvents) {}

    public function handle(OrderPlaced $event): void
    {
        $this->handledEvents->record(self::class, $event);
    }
}
