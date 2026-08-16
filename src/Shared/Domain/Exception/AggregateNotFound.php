<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use Shared\Domain\Identifier\Identifier;

final class AggregateNotFound extends DomainException
{
    public static function withId(Identifier $id): self
    {
        return new self(sprintf('No aggregate was found with identifier "%s".', $id->value()));
    }
}
