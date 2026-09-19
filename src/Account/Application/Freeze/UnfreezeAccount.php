<?php

declare(strict_types=1);

namespace Account\Application\Freeze;

use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Restores a frozen account after the restriction has been resolved. */
final readonly class UnfreezeAccount
{
    public function __construct(
        private AccountRepository $accounts,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(UnfreezeAccountCommand $command): void
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null) {
            throw AccountNotFound::create();
        }

        $account->unfreeze(
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
