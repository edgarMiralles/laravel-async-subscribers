<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Exceptions;

use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;
use InvalidArgumentException;

final class InvalidSubscriber extends InvalidArgumentException
{
    public static function missingHandleMethod(string $subscriber): self
    {
        return new self(sprintf('%s must declare a public handle() method.', $subscriber));
    }

    public static function handleMustTakeOneEvent(string $subscriber): self
    {
        return new self(sprintf(
            '%s::handle() must take exactly one parameter, type-hinted with a class that implements %s.',
            $subscriber,
            SerializableEvent::class,
        ));
    }
}
