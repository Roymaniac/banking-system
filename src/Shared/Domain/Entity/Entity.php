<?php

declare(strict_types=1);

namespace Shared\Domain\Entity;

abstract class Entity
{
    abstract public function equals(self $other): bool;
}
