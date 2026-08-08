<?php

declare(strict_types=1);

namespace Shared\Domain\Identifier;

use Shared\Domain\ValueObject\ValueObject;

abstract class Identifier extends ValueObject
{
    abstract public function value(): string;

    /**
     * Returns the scalar representation of this identifier
     * @return array<string, string>
     */
    final protected function toArray(): array
    {
        return ['value' => $this->value()];
    }

    /**
     * Returns the string representation of this identifier
     * @return string
     */
    final public function __toString(): string
    {
        return $this->value();
    }
}
