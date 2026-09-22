<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Persistence;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Domain\Exception\ConcurrencyException;
use Transaction\Domain\Common\ValueObject\TransactionAmount;
use Transaction\Domain\Common\ValueObject\TransactionId;
use Transaction\Domain\Common\ValueObject\TransactionReference;
use Transaction\Domain\Transfer\Repository\TransferRepository;
use Transaction\Domain\Transfer\Transfer;

/** Stores immutable completed-transfer records. */
final readonly class DatabaseTransferRepository implements TransferRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(Transfer $transfer): void
    {
        $storedVersion = $this->connection->table('transfers')
            ->where('id', $transfer->id()->value())
            ->value('version');

        $values = [
            'sender_account_id' => $transfer->senderAccountId()->value(),
            'recipient_account_id' => $transfer->recipientAccountId()->value(),
            'ledger_entry_id' => $transfer->ledgerEntryId()->value(),
            'reference' => $transfer->reference()->value(),
            'minor_units' => $transfer->amount()->minorUnits(),
            'currency' => $transfer->amount()->currency()->value(),
            'completed_at' => $transfer->completedAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $transfer->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('transfers')
                ->insert(['id' => $transfer->id()->value(), ...$values]);

            return;
        }

        $expectedVersion = $transfer->version() - 1;
        $updated = $this->connection->table('transfers')
            ->where('id', $transfer->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($transfer->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(TransactionId $id): ?Transfer
    {
        return $this->hydrate($this->connection->table('transfers')
            ->where('id', $id->value())
            ->first());
    }

    public function findByReference(TransactionReference $reference): ?Transfer
    {
        return $this->hydrate($this->connection->table('transfers')
            ->where('reference', $reference->value())
            ->first());
    }

    public function referenceExists(TransactionReference $reference): bool
    {
        return $this->connection->table('transfers')
            ->where('reference', $reference->value())
            ->exists();
    }

    private function hydrate(?object $record): ?Transfer
    {
        if ($record === null) {
            return null;
        }

        return Transfer::reconstitute(
            new TransactionId($record->id),
            new AccountId($record->sender_account_id),
            new AccountId($record->recipient_account_id),
            new LedgerEntryId($record->ledger_entry_id),
            new TransactionReference($record->reference),
            new TransactionAmount((int) $record->minor_units, new LedgerCurrency($record->currency)),
            new DateTimeImmutable($record->completed_at),
            (int) $record->version
        );
    }
}
