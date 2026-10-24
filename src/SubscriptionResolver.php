<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Attributes\OnQueue;
use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;
use EdgarMiralles\AsyncSubscribers\Exceptions\InvalidSubscriber;
use ReflectionClass;
use ReflectionNamedType;

final class SubscriptionResolver
{
    /**
     * @param  class-string<AsyncSubscriber>  $subscriber
     *
     * @throws InvalidSubscriber
     */
    public function resolve(string $subscriber): Subscription
    {
        $class = new ReflectionClass($subscriber);

        if (! $class->hasMethod('handle') || ! $class->getMethod('handle')->isPublic()) {
            throw InvalidSubscriber::missingHandleMethod($subscriber);
        }

        $handle = $class->getMethod('handle');
        $type = ($handle->getParameters()[0] ?? null)?->getType();

        if (
            $handle->getNumberOfRequiredParameters() !== 1
            || ! $type instanceof ReflectionNamedType
            || $type->isBuiltin()
            || ! is_subclass_of($type->getName(), SerializableEvent::class)
        ) {
            throw InvalidSubscriber::handleMustTakeOneEvent($subscriber);
        }

        /** @var class-string<SerializableEvent> $event */
        $event = $type->getName();
        $onQueue = ($class->getAttributes(OnQueue::class)[0] ?? null)?->newInstance();

        return new Subscription($subscriber, $event, $onQueue?->queue);
    }
}
