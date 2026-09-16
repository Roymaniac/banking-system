<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

/**
 * Carries the submitted email into the reset-request use case.
 */
final readonly class RequestPasswordResetCommand
{
    public function __construct(
        public string $email,
    ) {}
}
