<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers\Attributes;

use Attribute;
use UnitEnum;

use function Illuminate\Support\enum_value;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class OnQueue
{
    public string $queue;

    public function __construct(UnitEnum | string $queue)
    {
        $this->queue = (string) enum_value($queue);
    }
}
