<?php

declare(strict_types=1);

namespace Ledger\Application\Posting;

use Ledger\Domain\Entry\Exception\LedgerEntryNotFound;
use Ledger\Domain\Entry\Exception\LedgerNotFound;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Posting\ValueObject\PostingAmount;
use Ledger\Domain\Posting\ValueObject\PostingId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Adds a validated debit or credit line to a draft entry. */
final readonly class AddPosting
{
    public function __construct(
        private LedgerEntryRepository $entries,
        private LedgerRepository $ledgers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(AddPostingCommand $command): PostingId
    {
        $entry = $this->entries->findById($command->entryId);

        if ($entry === null) {
            throw LedgerEntryNotFound::create();
        }

        $ledger = $this->ledgers->findById($command->ledgerId);

        if ($ledger === null) {
            throw LedgerNotFound::create();
        }

        $postingId = new PostingId($this->uuidGenerator->generate()->value());
        $entry->addPosting(
            postingId: $postingId,
            ledgerId: $command->ledgerId,
            side: $command->side,
            amount: new PostingAmount($command->minorUnits, $ledger->currency()),
            addedAt: $this->clock->now(),
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($entry): array {
            $this->entries->save($entry);

            return $entry->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $postingId;
    }
}
