<?php

declare(strict_types=1);

namespace Ledger\Application\Posting;

use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Identifier\CorrelationId;

/** Carries one debit or credit line into a draft ledger entry. */
final readonly class AddPostingCommand
{
    public function __construct(
        public LedgerEntryId $entryId,
        public LedgerId $ledgerId,
        public PostingSide $side,
        public int $minorUnits,
        public ?CorrelationId $correlationId = null,
    ) {}
}
