<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry;

use DateTimeImmutable;
use InvalidArgumentException;
use Ledger\Domain\Entry\Event\LedgerEntryDrafted;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * A LedgerEntry is one financial event and begins as a harmless draft.
 * It becomes balance-affecting only after balanced postings are added later.
 */
final class LedgerEntry extends AggregateRoot
{
    private function __construct(
        private readonly LedgerEntryId $id,
        private readonly LedgerId $ledgerId,
        private readonly EntryReference $reference,
        private readonly EntryDescription $description,
        private readonly DateTimeImmutable $occurredAt,
        private readonly DateTimeImmutable $recordedAt,
        private EntryStatus $status = EntryStatus::Draft,
    ) {}

    public static function draft(
        LedgerEntryId $id,
        LedgerId $ledgerId,
        EntryReference $reference,
        EntryDescription $description,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $recordedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        if ($occurredAt > $recordedAt) {
            throw new InvalidArgumentException('A ledger entry cannot occur after it is recorded.');
        }

        $entry = new self($id, $ledgerId, $reference, $description, $occurredAt, $recordedAt);
        $entry->record(new LedgerEntryDrafted(
            $eventId,
            $id,
            $recordedAt,
            $ledgerId,
            $reference,
            $correlationId,
        ));

        return $entry;
    }

    /** Rebuilds a stored entry without recording another draft event. */
    public static function reconstitute(
        LedgerEntryId $id,
        LedgerId $ledgerId,
        EntryReference $reference,
        EntryDescription $description,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $recordedAt,
        EntryStatus $status,
        int $version,
    ): self {
        $entry = new self($id, $ledgerId, $reference, $description, $occurredAt, $recordedAt, $status);
        $entry->reconstituteAtVersion($version);

        return $entry;
    }

    public function id(): LedgerEntryId
    {
        return $this->id;
    }

    public function ledgerId(): LedgerId
    {
        return $this->ledgerId;
    }

    public function reference(): EntryReference
    {
        return $this->reference;
    }

    public function description(): EntryDescription
    {
        return $this->description;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function status(): EntryStatus
    {
        return $this->status;
    }
}
