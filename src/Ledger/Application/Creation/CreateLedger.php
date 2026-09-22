<?php

declare(strict_types=1);

namespace Ledger\Application\Creation;

use Account\Domain\Account\Repository\AccountRepository;
use Ledger\Domain\Ledger\Exception\AccountNotEligibleForLedger;
use Ledger\Domain\Ledger\Exception\LedgerAlreadyExists;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates the one authoritative ledger belonging to an active account. */
final readonly class CreateLedger
{
    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(CreateLedgerCommand $command): Ledger
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null || ! $account->isActive()) {
            throw AccountNotEligibleForLedger::create();
        }

        if ($this->ledgers->existsForAccount($command->accountId)) {
            throw LedgerAlreadyExists::create();
        }

        $now = $this->clock->now();
        $ledger = Ledger::create(
            id: new LedgerId($this->uuidGenerator->generate()->value()),
            accountId: $command->accountId,
            currency: new LedgerCurrency($account->currency()->value()),
            createdAt: $now,
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($ledger): array {
            $this->ledgers->save($ledger);

            return $ledger->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $ledger;
    }
}
