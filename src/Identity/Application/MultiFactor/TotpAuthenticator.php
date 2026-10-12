<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\EmailAddress;

/** Generates and verifies secrets understood by standard authenticator apps. */
interface TotpAuthenticator
{
    public function generateSecret(): string;

    public function provisioningUri(EmailAddress $email, string $issuer, string $secret): string;

    public function verify(string $secret, string $code, DateTimeImmutable $at): bool;
}
