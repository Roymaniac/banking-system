<?php

declare(strict_types=1);

namespace Ledger\Application\Posting;

use Ledger\Domain\Entry\Exception\LedgerEntryNotFound;
use Ledger\Domain\Entry\Repository\LedgerEntryRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Makes a balanced draft permanent and eligible for balance projection. */
final readonly class PostLedgerEntry
{
    public function __construct(
        private LedgerEntryRepository $entries,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(PostLedgerEntryCommand $command): void
    {
        $entry = $this->entries->findById($command->entryId);

        if ($entry === null) {
            throw LedgerEntryNotFound::create();
        }

        $entry->post(
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($entry): array {
            $this->entries->save($entry);

            return $entry->pullDomainEvents();
        });

        $this->events->publish($domainEvents);
    }
}
