<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\Posting;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Exception\ConcurrencyException;

/** Stores ledger-entry headers separately from their future posting lines. */
final readonly class DatabaseLedgerEntryRepository implements LedgerEntryRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function save(LedgerEntry $entry): void
    {
        $storedVersion = $this->connection->table('ledger_entries')
            ->where('id', $entry->id()->value())
            ->value('version');
        $utc = new DateTimeZone('UTC');
        $values = [
            'ledger_id' => $entry->ledgerId()->value(),
            'reference' => $entry->reference()->value(),
            'description' => $entry->description()->value(),
            'occurred_at' => $entry->occurredAt()->setTimezone($utc),
            'recorded_at' => $entry->recordedAt()->setTimezone($utc),
            'status' => $entry->status()->value,
            'version' => $entry->version(),
        ];

        if ($storedVersion === null) {
            $this->connection->table('ledger_entries')->insert([
                'id' => $entry->id()->value(),
                ...$values,
            ]);

        } else {
            $expectedVersion = $entry->version() - 1;
            $updated = $this->connection->table('ledger_entries')
                ->where('id', $entry->id()->value())
                ->where('version', $expectedVersion)
                ->update($values);

            if ($updated !== 1) {
                throw ConcurrencyException::forAggregate($entry->id(), $expectedVersion, (int) $storedVersion);
            }
        }

        foreach ($entry->postings() as $posting) {
            $this->connection->table('ledger_postings')->updateOrInsert(
                ['id' => $posting->id()->value()],
                [
                    'entry_id' => $entry->id()->value(),
                    'ledger_id' => $posting->ledgerId()->value(),
                    'side' => $posting->side()->value,
                    'minor_units' => $posting->amount()->minorUnits(),
                    'currency' => $posting->amount()->currency()->value(),
                ],
            );
        }
    }

    public function findById(LedgerEntryId $id): ?LedgerEntry
    {
        return $this->hydrate($this->connection->table('ledger_entries')->where('id', $id->value())->first());
    }

    public function findByReference(LedgerId $ledgerId, EntryReference $reference): ?LedgerEntry
    {
        return $this->hydrate($this->connection->table('ledger_entries')
            ->where('ledger_id', $ledgerId->value())
            ->where('reference', $reference->value())
            ->first());
    }

    public function referenceExists(LedgerId $ledgerId, EntryReference $reference): bool
    {
        return $this->connection->table('ledger_entries')
            ->where('ledger_id', $ledgerId->value())
            ->where('reference', $reference->value())
            ->exists();
    }

    private function hydrate(?object $record): ?LedgerEntry
    {
        if ($record === null) {
            return null;
        }

        $postings = $this->connection->table('ledger_postings')
            ->where('entry_id', $record->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $posting): Posting => new Posting(
                new PostingId($posting->id),
                new LedgerId($posting->ledger_id),
                PostingSide::from($posting->side),
                new PostingAmount(
                    (int) $posting->minor_units,
                    new LedgerCurrency($posting->currency),
                ),
            ))
            ->all();

        return LedgerEntry::reconstitute(
            id: new LedgerEntryId($record->id),
            ledgerId: new LedgerId($record->ledger_id),
            reference: new EntryReference($record->reference),
            description: new EntryDescription($record->description),
            occurredAt: new DateTimeImmutable($record->occurred_at),
            recordedAt: new DateTimeImmutable($record->recorded_at),
            status: EntryStatus::from($record->status),
            version: (int) $record->version,
            postings: $postings,
        );
    }
}
