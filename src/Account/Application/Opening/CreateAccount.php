<?php

declare(strict_types=1);

namespace Account\Application\Opening;

use Account\Domain\Account\Account;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\Exception\CustomerNotFound;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates the internal account record for an existing customer. */
final readonly class CreateAccount
{
    public function __construct(
        private CustomerRepository $customers,
        private AccountRepository $accounts,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(CreateAccountCommand $command): Account
    {
        if ($this->customers->findById($command->customerId) === null) {
            throw CustomerNotFound::create();
        }

        $now = $this->clock->now();
        $account = Account::create(
            id: new AccountId($this->uuidGenerator->generate()->value()),
            customerId: $command->customerId,
            type: $command->type,
            currency: new CurrencyCode($command->currency),
            createdAt: $now,
            eventId: $this->uuidGenerator->generate(),
            correlationId: $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($account): array {
            $this->accounts->save($account);

            return $account->pullDomainEvents();
        });

        // Publish only after persistence succeeds, so consumers never receive
        // an event for an account that does not exist in the database.
        $this->events->publish($domainEvents);

        return $account;
    }
}
