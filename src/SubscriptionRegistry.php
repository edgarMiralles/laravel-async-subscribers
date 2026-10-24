<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

final class SubscriptionRegistry
{
    /** @var list<Subscription>|null */
    private ?array $subscriptions = null;

    public function __construct(
        private readonly SubscriptionCache $cache,
        private readonly SubscriptionFinder $finder,
    ) {}

    /** @return list<Subscription> */
    public function all(): array
    {
        return $this->subscriptions ??= $this->cache->read() ?? $this->finder->find();
    }

    /** @return list<Subscription> */
    public function subscribedTo(string $event): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Subscription $subscription): bool => $subscription->event === $event,
        ));
    }
}
