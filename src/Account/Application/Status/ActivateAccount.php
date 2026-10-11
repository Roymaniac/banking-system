<?php

declare(strict_types=1);

namespace Account\Application\Status;

use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Repository\AccountRepository;
use Compliance\Application\DueDiligence\CustomerDueDiligenceChecker;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Activates an account after all required provisioning is complete. */
final readonly class ActivateAccount
{
    public function __construct(
        private AccountRepository $accounts,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
        private CustomerDueDiligenceChecker $dueDiligence,
    ) {}

    public function handle(ActivateAccountCommand $command): void
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null) {
            throw AccountNotFound::create();
        }

        // A pending account cannot become usable until Compliance has
        // independently approved the customer identity evidence.
        $this->dueDiligence->assertApproved($account->customerId());

        $account->activate(
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
