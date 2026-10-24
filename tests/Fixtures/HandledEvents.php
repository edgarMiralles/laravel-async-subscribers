<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures;

use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;

final class HandledEvents
{
    /** @var list<array{subscriber: class-string, event: SerializableEvent}> */
    public array $handled = [];

    /** @param  class-string  $subscriber */
    public function record(string $subscriber, SerializableEvent $event): void
    {
        $this->handled[] = ['subscriber' => $subscriber, 'event' => $event];
    }
}
