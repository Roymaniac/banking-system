<?php

declare(strict_types=1);

namespace Shared\Domain\Entity;

use Shared\Domain\ValueObject\ValueObject;

abstract class Entity
{
    /**
     * Returns the identifier of the entity
     */
    abstract public function id(): ValueObject;

    /**
     * Compares two entities for equality
     */
    final public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->id()->equals($other->id());
    }
}
