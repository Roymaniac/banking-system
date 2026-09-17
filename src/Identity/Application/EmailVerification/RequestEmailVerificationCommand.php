<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use Identity\Domain\User\ValueObject\UserId;

/**
 * Identifies the user who needs a fresh verification link.
 */
final readonly class RequestEmailVerificationCommand
{
    public function __construct(
        public UserId $userId,
    ) {}
}
