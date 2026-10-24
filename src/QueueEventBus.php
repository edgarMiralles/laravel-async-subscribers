<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Contracts\EventBus;
use EdgarMiralles\AsyncSubscribers\Contracts\SerializableEvent;
use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Throwable;

final class QueueEventBus implements EventBus
{
    public function __construct(
        private readonly SubscriptionRegistry $registry,
        private readonly Dispatcher $dispatcher,
        private readonly Repository $config,
    ) {}

    /** Queues every subscriber before rethrowing the first failure, so one bad queue does not starve the rest. */
    public function dispatch(SerializableEvent ...$events): void
    {
        $firstFailure = null;

        foreach ($events as $event) {
            $subscriptions = $this->registry->subscribedTo($event::class);

            if ($subscriptions === []) {
                continue;
            }

            $payload = $event->toArray();

            foreach ($subscriptions as $subscription) {
                try {
                    $this->dispatcher->dispatch($this->jobFor($subscription, $payload));
                } catch (Throwable $failure) {
                    if ($firstFailure !== null) {
                        report($failure);

                        continue;
                    }

                    $firstFailure = $failure;
                }
            }
        }

        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function jobFor(Subscription $subscription, array $payload): RunSubscriber
    {
        $job = new RunSubscriber(
            $subscription->subscriber,
            $subscription->event,
            $payload,
            $this->config->get('async-subscribers.tries'),
            $this->config->get('async-subscribers.backoff'),
        );

        $job->onConnection($this->config->get('async-subscribers.connection'))
            ->onQueue($subscription->queue ?? $this->config->get('async-subscribers.queue'));

        if ($this->config->get('async-subscribers.after_commit')) {
            $job->afterCommit();
        } else {
            $job->beforeCommit();
        }

        return $job;
    }
}
