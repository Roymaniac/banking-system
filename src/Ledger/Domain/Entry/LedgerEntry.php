<?php

declare(strict_types=1);

namespace Ledger\Domain\Entry;

use DateTimeImmutable;
use InvalidArgumentException;
use Ledger\Domain\Entry\Event\LedgerEntryDrafted;
use Ledger\Domain\Entry\Event\LedgerEntryPosted;
use Ledger\Domain\Entry\Event\PostingAdded;
use Ledger\Domain\Entry\Exception\DuplicateLedgerPosting;
use Ledger\Domain\Entry\Exception\InsufficientPostings;
use Ledger\Domain\Entry\Exception\OriginatingLedgerPostingRequired;
use Ledger\Domain\Entry\Exception\PostedEntryCannotBeChanged;
use Ledger\Domain\Entry\Exception\PostingCurrencyMismatch;
use Ledger\Domain\Entry\Exception\UnbalancedLedgerEntry;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\EntryStatus;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Ledger\Domain\Posting\Posting;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Ledger\Domain\Posting\ValueObject\PostingSide;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * A LedgerEntry is one financial event and begins as a harmless draft.
 * It becomes balance-affecting only after balanced postings are added later.
 */
final class LedgerEntry extends AggregateRoot
{
    /** @var list<Posting> */
    private array $postings;

    private function __construct(
        private readonly LedgerEntryId $id,
        private readonly LedgerId $ledgerId,
        private readonly EntryReference $reference,
        private readonly EntryDescription $description,
        private readonly DateTimeImmutable $occurredAt,
        private readonly DateTimeImmutable $recordedAt,
        private EntryStatus $status = EntryStatus::Draft,
        array $postings = [],
    ) {
        $this->postings = $postings;
    }

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
        array $postings = [],
    ): self {
        $entry = new self($id, $ledgerId, $reference, $description, $occurredAt, $recordedAt, $status, $postings);
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

    /** @return list<Posting> */
    public function postings(): array
    {
        return $this->postings;
    }

    public function addPosting(
        PostingId $postingId,
        LedgerId $ledgerId,
        PostingSide $side,
        PostingAmount $amount,
        DateTimeImmutable $addedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $this->guardDraft();

        foreach ($this->postings as $posting) {
            if ($posting->ledgerId()->equals($ledgerId)) {
                throw DuplicateLedgerPosting::create();
            }

            if (! $posting->amount()->currency()->equals($amount->currency())) {
                throw PostingCurrencyMismatch::create();
            }
        }

        $this->postings[] = new Posting($postingId, $ledgerId, $side, $amount);
        $this->record(new PostingAdded(
            $eventId,
            $this->id,
            $this->version() + 1,
            $addedAt,
            $postingId,
            $ledgerId,
            $side,
            $amount,
            $correlationId,
        ));
    }

    public function post(
        DateTimeImmutable $postedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        $this->guardDraft();

        if (count($this->postings) < 2) {
            throw InsufficientPostings::create();
        }

        $debits = $this->sum(PostingSide::Debit);
        $credits = $this->sum(PostingSide::Credit);

        if ($debits === 0 || $credits === 0) {
            throw InsufficientPostings::create();
        }

        $includesOriginatingLedger = false;

        foreach ($this->postings as $posting) {
            if ($posting->ledgerId()->equals($this->ledgerId)) {
                $includesOriginatingLedger = true;
                break;
            }
        }

        if (! $includesOriginatingLedger) {
            throw OriginatingLedgerPostingRequired::create();
        }

        if ($debits !== $credits) {
            throw UnbalancedLedgerEntry::create();
        }

        $currency = $this->postings[0]->amount()->currency();
        $this->status = EntryStatus::Posted;
        $this->record(new LedgerEntryPosted(
            $eventId,
            $this->id,
            $this->version() + 1,
            $postedAt,
            count($this->postings),
            $debits,
            $currency,
            $correlationId,
        ));
    }

    private function guardDraft(): void
    {
        if ($this->status !== EntryStatus::Draft) {
            throw PostedEntryCannotBeChanged::create();
        }
    }

    private function sum(PostingSide $side): int
    {
        return array_reduce(
            $this->postings,
            fn (int $total, Posting $posting): int => $posting->side() === $side
                ? $total + $posting->amount()->minorUnits()
                : $total,
            0,
        );
    }
}
