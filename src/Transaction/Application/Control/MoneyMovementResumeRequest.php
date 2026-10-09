<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use DateTimeImmutable;
use Shared\Domain\Identifier\Uuid;

/** Carries the safe details needed for independent resume approval. */
final readonly class MoneyMovementResumeRequest
{
    public function __construct(
        public Uuid $id,
        public Uuid $requestedBy,
        public string $reason,
        public ?int $controlRevision,
        public string $status,
        public DateTimeImmutable $requestedAt,
        public DateTimeImmutable $expiresAt,
        public ?Uuid $approvedBy = null,
        public ?DateTimeImmutable $approvedAt = null,
        public ?Uuid $closedBy = null,
        public ?string $closureReason = null,
        public ?DateTimeImmutable $closedAt = null,
    ) {}
}
