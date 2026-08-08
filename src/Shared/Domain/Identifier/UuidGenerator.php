<?php

declare(strict_types=1);

namespace Shared\Domain\Identifier;

interface UuidGenerator
{
    public function generate(): Uuid;
}
