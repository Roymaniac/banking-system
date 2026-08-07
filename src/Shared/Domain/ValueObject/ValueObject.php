<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use JsonSerializable;

abstract class ValueObject implements JsonSerializable
{

    /**
     * Return the scalar representation of this value object
     *
     * @return array<string, mixed>
     */
    abstract protected function toArray(): array;

    final public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->toArray() === $other->toArray();
    }

    /**
     * Serialize the value object into an array
     *
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
