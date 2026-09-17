<?php

declare(strict_types=1);

namespace Identity\Application\PasswordReset;

use Shared\Domain\Identifier\CorrelationId;

/**
 * Carries a reset token and proposed password into the reset use case.
 */
final readonly class ResetPasswordCommand
{
    public function __construct(
        public string $token,
        public string $newPassword,
        public ?CorrelationId $correlationId = null,
    ) {}
}
