<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\Exception;

use DomainException;

/** Raised when staff onboarding does not reference a verified sign-in identity. */
final class EligibleStaffIdentityRequired extends DomainException
{
    public static function create(): self
    {
        return new self('A verified sign-in identity is required before staff can be hired.');
    }
}
