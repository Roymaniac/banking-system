<?php

declare(strict_types=1);

namespace Transaction\Domain\Risk\Exception;

use Shared\Domain\Exception\DomainException;

final class InvalidTransferRiskCaseTransition extends DomainException
{
    public static function alreadyClaimed(): self
    {
        return new self('This risk case is already assigned to another reviewer.');
    }

    public static function notClaimedByReviewer(): self
    {
        return new self('Claim this risk case before resolving it.');
    }

    public static function resolved(): self
    {
        return new self('This risk case has already been resolved.');
    }

    public static function changed(): self
    {
        return new self('This risk case changed while it was being reviewed.');
    }
}
