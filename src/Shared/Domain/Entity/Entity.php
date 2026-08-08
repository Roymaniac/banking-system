<?php

declare(strict_types=1);

namespace Shared\Domain\Entity;

use Shared\Domain\Identifier\Identifier;

abstract class Entity
{
    /**
     * Returns the identifier of the entity
     */
    abstract public function id(): Identifier;

    /**
     * Compares two entities for equality
     */
    final public function equals(self $other): bool
    {
        return static::class === $other::class
            && $this->id()->equals($other->id());
    }
}
