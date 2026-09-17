<?php

declare(strict_types=1);

namespace Identity\Application\Authentication;

/**
 * Carries login input into the authentication use case.
 *
 * The plain password exists only for the duration of this request and must
 * never be logged, serialized, or stored.
 */
final readonly class AuthenticateUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
