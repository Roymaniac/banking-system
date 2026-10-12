<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

/** The secret and URI shown once while an authenticator is being enrolled. */
final readonly class MultiFactorSetup
{
    public function __construct(
        public string $secret,
        public string $provisioningUri,
    ) {}
}
