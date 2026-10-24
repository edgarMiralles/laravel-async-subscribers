<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Subscribers;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;
use EdgarMiralles\AsyncSubscribers\Tests\Fixtures\Events\OrderShipped;

abstract class AbstractAuditSubscriber implements AsyncSubscriber
{
    public function handle(OrderShipped $event): void {}
}
