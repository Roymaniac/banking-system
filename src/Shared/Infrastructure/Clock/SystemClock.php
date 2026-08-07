<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Clock;

use DateTimeImmutable;
use Shared\Contracts\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
