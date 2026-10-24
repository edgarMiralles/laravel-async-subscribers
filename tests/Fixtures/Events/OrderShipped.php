<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events;

use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;

final readonly class OrderShipped implements SerializableEvent
{
    public function __construct(public string $orderId) {}

    public function toArray(): array
    {
        return ['order_id' => $this->orderId];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id']);
    }
}
