<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

use DateTimeImmutable;
use Shared\Domain\Identifier\Uuid;

/** A privacy-safe view of one transfer-risk investigation. */
final readonly class TransferRiskCase
{
    public function __construct(
        public Uuid $id,
        public string $accountLastFour,
        public string $rule,
        public string $status,
        public int $windowMinutes,
        public int $maximumTransfers,
        public DateTimeImmutable $detectedAt,
        public ?Uuid $claimedBy = null,
        public ?DateTimeImmutable $claimedAt = null,
        public ?Uuid $resolvedBy = null,
        public ?string $resolution = null,
        public ?string $resolutionNote = null,
        public ?DateTimeImmutable $resolvedAt = null,
    ) {}
}
