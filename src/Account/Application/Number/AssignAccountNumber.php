<?php

declare(strict_types=1);

namespace Account\Application\Number;

use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Exception\AccountNumberGenerationFailed;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountNumber;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;

/** Generates and permanently assigns a unique number to one account. */
final readonly class AssignAccountNumber
{
    private const MAX_GENERATION_ATTEMPTS = 10;

    public function __construct(
        private AccountRepository $accounts,
        private AccountNumberGenerator $numberGenerator,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(AssignAccountNumberCommand $command): AccountNumber
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null) {
            throw AccountNotFound::create();
        }

        $number = $this->generateUniqueNumber();
        $account->assignNumber(
            $number,
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );

        $domainEvents = $this->transactions->run(function () use ($account): array {
            $this->accounts->save($account);

            return $account->pullDomainEvents();
        });

        $this->events->publish($domainEvents);

        return $number;
    }

    private function generateUniqueNumber(): AccountNumber
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $candidate = $this->numberGenerator->generate();

            if (! $this->accounts->numberExists($candidate)) {
                return $candidate;
            }
        }

        // A limit prevents an unhealthy generator from looping forever.
        throw AccountNumberGenerationFailed::create();
    }
}
