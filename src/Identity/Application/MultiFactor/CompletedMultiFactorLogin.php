<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use Identity\Domain\User\ValueObject\UserId;

final readonly class CompletedMultiFactorLogin
{
    public function __construct(
        public UserId $userId,
        public string $deviceName,
        public MultiFactorChallengePurpose $purpose,
        public ?string $contextHash = null,
    ) {}
}
