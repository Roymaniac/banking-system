<?php

declare(strict_types=1);

namespace Shared\Contracts;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
