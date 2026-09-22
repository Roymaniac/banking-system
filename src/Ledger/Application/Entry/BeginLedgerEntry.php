<?php

declare(strict_types=1);

namespace Ledger\Application\Entry;

use Ledger\Domain\Entry\Exception\DuplicateEntryReference;
use Ledger\Domain\Entry\Exception\LedgerNotFound;
use Ledger\Domain\Entry\LedgerEntry;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Entry\ValueObject\EntryDescription;
use Ledger\Domain\Entry\ValueObject\EntryReference;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Begins an idempotent draft entry that cannot yet change any balance. */
final readonly class BeginLedgerEntry
{
    public function __construct(
        private LedgerRepository $ledgers,
        private LedgerEntryRepository $entries,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(BeginLedgerEntryCommand $command): LedgerEntry
    {
        if ($this->ledgers->findById($command->ledgerId) === null) {
            throw LedgerNotFound::create();
        }

        $reference = new EntryReference($command->reference);

        if ($this->entries->referenceExists($command->ledgerId, $reference)) {
            throw DuplicateEntryReference::create();
        }

        $entry = LedgerEntry::draft(
            id: new LedgerEntryId($this->uuidGenerator->generate()->value()),
            ledgerId: $command->ledgerId,
            reference: $reference,
            description: new EntryDescription($command->description),
            occurredAt: $command->occurredAt,
            recordedAt: $this->clock->now(),
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($entry): array {
            $this->entries->save($entry);

            return $entry->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $entry;
    }
}
