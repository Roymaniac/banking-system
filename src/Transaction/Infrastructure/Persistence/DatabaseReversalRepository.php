<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Shared\Domain\Exception\ConcurrencyException;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Reversal\Repository\ReversalRepository;
use Transaction\Domain\Reversal\Reversal;
use Transaction\Domain\Reversal\ValueObject\ReversalReason;

/** Stores the permanent link between an original entry and its reversal entry. */
final readonly class DatabaseReversalRepository implements ReversalRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Reversal $reversal): void
    {
        $storedVersion = $this->connection->table('transaction_reversals')
            ->where('id', $reversal->id()->value())
            ->value('version');

        $values = [
            'original_ledger_entry_id' => $reversal->originalLedgerEntryId()->value(),
            'reversal_ledger_entry_id' => $reversal->reversalLedgerEntryId()->value(),
            'reference' => $reversal->reference()->value(),
            'reason' => $reversal->reason()->value(),
            'completed_at' => $reversal->completedAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $reversal->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('transaction_reversals')
                ->insert(['id' => $reversal->id()->value(), ...$values]);

            return;
        }

        $expectedVersion = $reversal->version() - 1;
        $updated = $this->connection->table('transaction_reversals')
            ->where('id', $reversal->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($reversal->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(TransactionId $id): ?Reversal
    {
        return $this->hydrate($this->connection->table('transaction_reversals')
            ->where('id', $id->value())
            ->first());
    }

    public function findByReference(TransactionReference $reference): ?Reversal
    {
        return $this->hydrate($this->connection->table('transaction_reversals')
            ->where('reference', $reference->value())
            ->first());
    }

    public function referenceExists(TransactionReference $reference): bool
    {
        return $this->connection->table('transaction_reversals')
            ->where('reference', $reference->value())
            ->exists();
    }

    public function originalEntryWasReversed(LedgerEntryId $entryId): bool
    {
        return $this->connection->table('transaction_reversals')
            ->where('original_ledger_entry_id', $entryId->value())
            ->exists();
    }

    private function hydrate(?object $record): ?Reversal
    {
        if ($record === null) {
            return null;
        }

        return Reversal::reconstitute(
            new TransactionId($record->id),
            new LedgerEntryId($record->original_ledger_entry_id),
            new LedgerEntryId($record->reversal_ledger_entry_id),
            new TransactionReference($record->reference),
            new ReversalReason($record->reason),
            new DateTimeImmutable($record->completed_at),
            (int) $record->version
        );
    }
}
