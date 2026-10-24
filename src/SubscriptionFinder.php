<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

final class SubscriptionFinder
{
    /** @var array<string, list<Subscription>> */
    private static array $found = [];

    /** @param  array<string, string>  $directories  namespace prefix => directory */
    public function __construct(
        private readonly SubscriptionResolver $resolver,
        private readonly array $directories,
    ) {}

    /** @return list<Subscription> */
    public function find(): array
    {
        return self::$found[serialize($this->directories)] ??= $this->scan();
    }

    /** @return list<Subscription> */
    private function scan(): array
    {
        $subscriptions = [];

        foreach ($this->directories as $namespace => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = Finder::create()->files()->in($directory)->name('*Subscriber.php')->sortByName();

            foreach ($files as $file) {
                $class = rtrim($namespace, '\\') . '\\' . str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    substr($file->getRelativePathname(), 0, -strlen('.php')),
                );

                if ($this->isSubscriber($class)) {
                    $subscriptions[] = $this->resolver->resolve($class);
                }
            }
        }

        return $subscriptions;
    }

    /** @phpstan-assert-if-true class-string<AsyncSubscriber> $class */
    private function isSubscriber(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, AsyncSubscriber::class)
            && (new ReflectionClass($class))->isInstantiable();
    }
}
