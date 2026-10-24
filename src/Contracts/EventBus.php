<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Contracts;

interface EventBus
{
    public function dispatch(SerializableEvent ...$events): void;
}
