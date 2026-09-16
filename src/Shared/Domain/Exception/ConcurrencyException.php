<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use Shared\Domain\Identifier\Identifier;

final class ConcurrencyException extends DomainException
{
    public static function forAggregate(Identifier $id, int $expectedVersion, int $actualVersion): self
    {
        return new self(sprintf(
            'Aggregate "%s" was expected at version %d but is at version %d.',
            $id->value(),
            $expectedVersion,
            $actualVersion,
        ));
    }
}
