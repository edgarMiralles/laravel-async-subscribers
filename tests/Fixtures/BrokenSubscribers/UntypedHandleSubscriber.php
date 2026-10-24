<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Tests\Fixtures\BrokenSubscribers;

use EdgarMiralles\AsyncSubscribers\Contracts\AsyncSubscriber;

final class UntypedHandleSubscriber implements AsyncSubscriber
{
    public function handle($event): void {}
}
