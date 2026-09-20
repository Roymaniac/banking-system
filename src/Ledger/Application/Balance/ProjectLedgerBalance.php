<?php

declare(strict_types=1);

namespace Ledger\Application\Balance;

use Ledger\Domain\Balance\Exception\PostedEntryMissing;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Contracts\TransactionManager;

/** Updates read-optimized balances whenever a balanced entry is posted. */
final readonly class ProjectLedgerBalance
{
    public function __construct(
        private LedgerEntryRepository $entries,
        private BalanceProjectionRepository $balances,
        private TransactionManager $transactions,
    ) {}

    public function handle(LedgerEntryPosted $event): void
    {
        $entry = $this->entries->findById(new LedgerEntryId($event->aggregateId()->value()));

        if ($entry === null || $entry->status() !== EntryStatus::Posted) {
            throw PostedEntryMissing::create();
        }

        $this->transactions->run(
            fn (): mixed => $this->balances->apply($entry, $event->occurredOn()),
        );
    }
}
