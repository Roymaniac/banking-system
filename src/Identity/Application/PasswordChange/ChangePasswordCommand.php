<?php

declare(strict_types=1);

namespace Identity\Application\PasswordChange;

use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\CorrelationId;

/** Contains the authenticated proof and replacement password. */
final readonly class ChangePasswordCommand
{
    public function __construct(
        public UserId $userId,
        public string $currentPassword,
        public string $newPassword,
        public ?int $currentSessionId = null,
        public ?CorrelationId $correlationId = null,
    ) {}
}
