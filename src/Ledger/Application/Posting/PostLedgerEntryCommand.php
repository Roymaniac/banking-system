<?php

declare(strict_types=1);

namespace Ledger\Application\Posting;

use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Identifier\CorrelationId;

/** Identifies the balanced draft that should become permanent. */
final readonly class PostLedgerEntryCommand
{
    public function __construct(
        public LedgerEntryId $entryId,
        public ?CorrelationId $correlationId = null,
    ) {}
}
