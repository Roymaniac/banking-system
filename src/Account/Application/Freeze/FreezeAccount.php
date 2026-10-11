<?php

declare(strict_types=1);

namespace Account\Application\Freeze;

use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Restricts an active account and publishes the resulting audit event. */
final readonly class FreezeAccount
{
    public function __construct(
        private AccountRepository $accounts,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(FreezeAccountCommand $command): void
    {
        $domainEvents = $this->transactions->run(function () use ($command): array {
            // Money workflows use this same row lock, so a restriction cannot
            // race with a transaction that has already checked account status.
            $account = $this->accounts->findByIdForUpdate($command->accountId);

            if ($account === null) {
                throw AccountNotFound::create();
            }

            $account->freeze(
                $command->reason,
                $this->clock->now(),
                $this->uuidGenerator->generate(),
                $command->correlationId,
            );
            $this->accounts->save($account);

            return $account->pullDomainEvents();
        });

        $this->events->publish($domainEvents);
    }
}
