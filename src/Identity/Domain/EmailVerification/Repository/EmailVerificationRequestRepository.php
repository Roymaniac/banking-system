<?php

declare(strict_types=1);

namespace Identity\Domain\EmailVerification\Repository;

use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;

/**
 * Stores verification requests without exposing database details to Identity.
 */
interface EmailVerificationRequestRepository
{
    public function replace(EmailVerificationRequest $request): void;

    public function findByTokenHash(EmailVerificationTokenHash $tokenHash): ?EmailVerificationRequest;

    public function consume(EmailVerificationTokenHash $tokenHash): void;
}
