<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;

final readonly class Subscription
{
    /**
     * @param  class-string<AsyncSubscriber>  $subscriber
     * @param  class-string<SerializableEvent>  $event
     */
    public function __construct(
        public string $subscriber,
        public string $event,
        public ?string $queue,
    ) {}

    /** @return array{subscriber: class-string<AsyncSubscriber>, event: class-string<SerializableEvent>, queue: string|null} */
    public function toArray(): array
    {
        return [
            'subscriber' => $this->subscriber,
            'event' => $this->event,
            'queue' => $this->queue,
        ];
    }

    /** @param array{subscriber: class-string<AsyncSubscriber>, event: class-string<SerializableEvent>, queue: string|null} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['subscriber'], $data['event'], $data['queue']);
    }
}
