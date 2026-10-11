<?php

declare(strict_types=1);

namespace Identity\Application\Registration;

use Shared\Domain\Identifier\CorrelationId;

/** Carries the credentials needed to create a new sign-in identity. */
final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public ?CorrelationId $correlationId = null,
    ) {}
}
