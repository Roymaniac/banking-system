<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

abstract class ValueObject
{
    abstract public function equals(self $other): bool;
}
