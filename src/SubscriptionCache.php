<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

final class SubscriptionCache
{
    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** @return list<Subscription>|null */
    public function read(): ?array
    {
        if (! $this->exists()) {
            return null;
        }

        /** @var list<array{subscriber: class-string<Contracts\AsyncSubscriber>, event: class-string<Contracts\SerializableEvent>, queue: string|null}> $rows */
        $rows = require $this->path;

        return array_map(Subscription::fromArray(...), $rows);
    }

    /** @param  list<Subscription>  $subscriptions */
    public function write(array $subscriptions): void
    {
        $rows = array_map(fn (Subscription $subscription): array => $subscription->toArray(), $subscriptions);

        file_put_contents($this->path, '<?php return ' . var_export($rows, true) . ';' . PHP_EOL, LOCK_EX);
    }

    public function clear(): void
    {
        if ($this->exists()) {
            unlink($this->path);
        }
    }
}
