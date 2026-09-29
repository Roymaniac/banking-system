<?php

declare(strict_types=1);

namespace Reporting\Application\Ledger\View;

/** One debit or credit leg belonging to a journal entry. */
final readonly class LedgerPostingView
{
    public function __construct(
        public string $postingId,
        public string $ledgerId,
        public string $accountId,
        public ?string $accountNumber,
        public string $side,
        public int $minorUnits,
        public string $currency,
    ) {}
}
