<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Balance\Exception\UnpostedEntryCannotBeProjected;
use Ledger\Domain\Balance\LedgerBalance;
use Ledger\Domain\Balance\Repository\BalanceProjectionRepository;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\ValueObject\PostingSide;

/** Builds balances once from each immutable posted entry. */
final readonly class DatabaseBalanceProjectionRepository implements BalanceProjectionRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function apply(LedgerEntry $entry, DateTimeImmutable $projectedAt): void
    {
        if ($entry->status() !== EntryStatus::Posted) {
            throw UnpostedEntryCannotBeProjected::create();
        }

        $projectedAt = $projectedAt->setTimezone(new DateTimeZone('UTC'));

        foreach ($entry->postings() as $posting) {
            // This insert is the idempotency gate. A replay returns zero and
            // therefore cannot add the same posting to a balance twice.
            $inserted = $this->connection->table('ledger_balance_contributions')->insertOrIgnore([
                'entry_id' => $entry->id()->value(),
                'ledger_id' => $posting->ledgerId()->value(),
                'side' => $posting->side()->value,
                'minor_units' => $posting->amount()->minorUnits(),
                'projected_at' => $projectedAt,
            ]);

            if ($inserted === 0) {
                continue;
            }

            // Only initialize a missing balance. Updating an existing row here
            // would erase totals accumulated from earlier ledger entries.
            $this->connection->table('ledger_balances')->insertOrIgnore([
                'ledger_id' => $posting->ledgerId()->value(),
                'currency' => $posting->amount()->currency()->value(),
                'debit_minor_units' => 0,
                'credit_minor_units' => 0,
                'balance_minor_units' => 0,
                'updated_at' => $projectedAt,
            ]);

            $amount = $posting->amount()->minorUnits();
            $increments = $posting->side() === PostingSide::Debit
                ? ['debit_minor_units' => $amount, 'balance_minor_units' => -$amount]
                : ['credit_minor_units' => $amount, 'balance_minor_units' => $amount];

            $this->connection->table('ledger_balances')
                ->where('ledger_id', $posting->ledgerId()->value())
                ->incrementEach($increments, ['updated_at' => $projectedAt]);
        }
    }

    public function find(LedgerId $ledgerId): ?LedgerBalance
    {
        return $this->hydrate($this->connection->table('ledger_balances')
            ->where('ledger_id', $ledgerId->value())
            ->first());
    }

    public function findForUpdate(LedgerId $ledgerId): ?LedgerBalance
    {
        return $this->hydrate($this->connection->table('ledger_balances')
            ->where('ledger_id', $ledgerId->value())
            ->lockForUpdate()
            ->first());
    }

    private function hydrate(?object $record): ?LedgerBalance
    {
        if ($record === null) {
            return null;
        }

        return new LedgerBalance(
            new LedgerId($record->ledger_id),
            new LedgerCurrency($record->currency),
            (int) $record->debit_minor_units,
            (int) $record->credit_minor_units,
        );
    }
}
