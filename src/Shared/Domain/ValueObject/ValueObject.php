<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use JsonSerializable;

abstract class ValueObject implements JsonSerializable
{

    abstract protected function toArray(): array;

    final public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->toArray() === $other->toArray();
    }

    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
