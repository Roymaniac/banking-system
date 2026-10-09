<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use DateTimeImmutable;

/** Represents one immutable safety-switch action for incident review. */
final readonly class MoneyMovementControlEventView
{
    public function __construct(
        public int $id,
        public string $action,
        public string $reason,
        public string $source,
        public ?string $actorUserId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
