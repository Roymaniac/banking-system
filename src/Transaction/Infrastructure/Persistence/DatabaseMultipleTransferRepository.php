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
use Transaction\Domain\MultipleTransfer\MultipleTransfer;
use Transaction\Domain\MultipleTransfer\MultipleTransferItem;
use Transaction\Domain\MultipleTransfer\Repository\MultipleTransferRepository;

/** Stores a multiple-transfer header together with all of its recipient items. */
final readonly class DatabaseMultipleTransferRepository implements MultipleTransferRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(MultipleTransfer $transfer): void
    {
        $storedVersion = $this->connection->table('multiple_transfers')
            ->where('id', $transfer->id()->value())
            ->value('version');

        $values = [
            'sender_account_id' => $transfer->senderAccountId()->value(),
            'ledger_entry_id' => $transfer->ledgerEntryId()->value(),
            'reference' => $transfer->reference()->value(),
            'total_minor_units' => $transfer->totalAmount()->minorUnits(),
            'currency' => $transfer->totalAmount()->currency()->value(),
            'completed_at' => $transfer->completedAt()->setTimezone(new DateTimeZone('UTC')),
            'version' => $transfer->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('multiple_transfers')
                ->insert(['id' => $transfer->id()->value(), ...$values]);

            foreach ($transfer->items() as $position => $item) {
                $this->connection->table('multiple_transfer_items')
                    ->insert([
                        'multiple_transfer_id' => $transfer->id()->value(),
                        'position' => $position,
                        'recipient_account_id' => $item->recipientAccountId()->value(),
                        'minor_units' => $item->amount()->minorUnits(),
                        'currency' => $item->amount()->currency()->value(),
                    ]);
            }

            return;
        }

        $expectedVersion = $transfer->version() - 1;
        $updated = $this->connection->table('multiple_transfers')
            ->where('id', $transfer->id()->value())
            ->where('version', $expectedVersion)
            ->update($values);

        if ($updated !== 1) {
            throw ConcurrencyException::forAggregate($transfer->id(), $expectedVersion, (int) $storedVersion);
        }
    }

    public function findById(TransactionId $id): ?MultipleTransfer
    {
        return $this->hydrate($this->connection->table('multiple_transfers')
            ->where('id', $id->value())
            ->first());
    }

    public function findByReference(TransactionReference $reference): ?MultipleTransfer
    {
        return $this->hydrate($this->connection->table('multiple_transfers')
            ->where('reference', $reference->value())
            ->first());
    }

    public function referenceExists(TransactionReference $reference): bool
    {
        return $this->connection->table('multiple_transfers')
            ->where('reference', $reference->value())
            ->exists();
    }

    private function hydrate(?object $record): ?MultipleTransfer
    {
        if ($record === null) {
            return null;
        }

        $items = $this->connection->table('multiple_transfer_items')
            ->where('multiple_transfer_id', $record->id)
            ->orderBy('position')
            ->get()->map(
                fn(object $item): MultipleTransferItem => new MultipleTransferItem(
                    new AccountId($item->recipient_account_id),
                    new TransactionAmount(
                        (int) $item->minor_units,
                        new LedgerCurrency($item->currency)
                    )
                )
            )->all();

        return MultipleTransfer::reconstitute(
            new TransactionId($record->id),
            new AccountId($record->sender_account_id),
            new LedgerEntryId($record->ledger_entry_id),
            new TransactionReference($record->reference),
            new TransactionAmount((int) $record->total_minor_units, new LedgerCurrency($record->currency)),
            $items,
            new DateTimeImmutable($record->completed_at),
            (int) $record->version
        );
    }
}
