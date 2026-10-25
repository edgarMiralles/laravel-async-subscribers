<?php

declare(strict_types=1);

namespace Shipping\Application;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderPlaced;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\HandledEvents;

final readonly class BookCourierSubscriber implements AsyncSubscriber
{
    public function __construct(private HandledEvents $handledEvents) {}

    public function handle(OrderPlaced $event): void
    {
        $this->handledEvents->record(self::class, $event);
    }
}
