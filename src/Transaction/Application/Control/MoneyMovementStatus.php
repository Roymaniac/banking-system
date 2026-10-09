<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use DateTimeImmutable;

/** Describes the operational switch without exposing financial records. */
final readonly class MoneyMovementStatus
{
    public function __construct(
        public bool $enabled,
        public ?string $reason,
        public string $source,
        public DateTimeImmutable $changedAt,
        public int $revision,
    ) {}
}
