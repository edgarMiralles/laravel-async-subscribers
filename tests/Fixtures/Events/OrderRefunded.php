<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events;

use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;

final readonly class OrderRefunded implements SerializableEvent
{
    public function __construct(public string $orderId, public int $amountInCents) {}

    public function toArray(): array
    {
        return ['order_id' => $this->orderId, 'amount_in_cents' => $this->amountInCents];
    }

    public static function fromArray(array $data): static
    {
        return new self($data['order_id'], $data['amount_in_cents']);
    }
}
