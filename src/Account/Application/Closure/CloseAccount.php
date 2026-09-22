<?php

declare(strict_types=1);

namespace Account\Application\Closure;

use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Permanently closes an eligible account and publishes its audit event. */
final readonly class CloseAccount
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountClosureBalanceChecker $balanceChecker,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(CloseAccountCommand $command): void
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null) {
            throw AccountNotFound::create();
        }

        // Ledger owns the authoritative balance, so closure asks through a
        // small contract instead of duplicating financial data in Account.
        $this->balanceChecker->assertZeroBalance($account->id());

        $account->close(
            $command->reason,
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($account): array {
            $this->accounts->save($account);

            return $account->pullDomainEvents();
        });

        $this->events->publish($domainEvents);
    }
}
