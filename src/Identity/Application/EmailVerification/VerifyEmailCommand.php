<?php

declare(strict_types=1);

namespace Identity\Application\EmailVerification;

use Shared\Domain\Identifier\CorrelationId;

/**
 * Carries the emailed token into the verification use case.
 */
final readonly class VerifyEmailCommand
{
    public function __construct(
        public string $token,
        public ?CorrelationId $correlationId = null,
    ) {}
}
