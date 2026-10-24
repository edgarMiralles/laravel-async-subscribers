<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers;

use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderShipped;

final class NewsletterSubscriber
{
    public function handle(OrderShipped $event): void {}
}
