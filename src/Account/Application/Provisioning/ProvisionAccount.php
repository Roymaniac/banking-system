<?php

declare(strict_types=1);

namespace Account\Application\Provisioning;

use Account\Application\Number\AccountNumberGenerator;
use Account\Domain\Account\Exception\AccountNotFound;
use Account\Domain\Account\Exception\AccountNumberGenerationFailed;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountNumber;
use Compliance\Application\DueDiligence\CustomerDueDiligenceChecker;
use Ledger\Domain\Ledger\Exception\LedgerAlreadyExists;
use Ledger\Domain\Ledger\Ledger;
use Ledger\Domain\Ledger\Repository\LedgerRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\UuidGenerator;

/** Completes every database step needed to make one pending account usable. */
final readonly class ProvisionAccount
{
    private const MAX_NUMBER_ATTEMPTS = 10;

    public function __construct(
        private AccountRepository $accounts,
        private LedgerRepository $ledgers,
        private AccountNumberGenerator $numberGenerator,
        private CustomerDueDiligenceChecker $dueDiligence,
        private Clock $clock,
        private UuidGenerator $ids,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ProvisionAccountCommand $command): ProvisionedAccount
    {
        /** @var array{result: ProvisionedAccount, events: list<DomainEvent>} $completed */
        $completed = $this->transactions->run(function () use ($command): array {
            // The row lock prevents two operators from provisioning the same
            // account at the same time.
            $account = $this->accounts->findByIdForUpdate($command->accountId);

            if ($account === null) {
                throw AccountNotFound::create();
            }

            $this->dueDiligence->assertApproved($account->customerId());

            if ($this->ledgers->existsForAccount($account->id())) {
                throw LedgerAlreadyExists::create();
            }

            $now = $this->clock->now();
            $account->assignNumber(
                $this->generateUniqueNumber(),
                $now,
                $this->ids->generate(),
                $command->correlationId,
            );

            // Each save advances one optimistic-lock version. Keeping both
            // saves inside this transaction still makes the workflow atomic.
            $this->accounts->save($account);
            $account->activate(
                $now,
                $this->ids->generate(),
                $command->correlationId,
            );
            $this->accounts->save($account);

            $ledger = Ledger::create(
                new LedgerId($this->ids->generate()->value()),
                $account->id(),
                new LedgerCurrency($account->currency()->value()),
                $now,
                $this->ids->generate(),
                $command->correlationId,
            );
            $this->ledgers->save($ledger);

            return [
                'result' => new ProvisionedAccount($account, $ledger),
                'events' => [...$account->pullDomainEvents(), ...$ledger->pullDomainEvents()],
            ];
        });

        $this->events->publish($completed['events']);

        return $completed['result'];
    }

    private function generateUniqueNumber(): AccountNumber
    {
        for ($attempt = 0; $attempt < self::MAX_NUMBER_ATTEMPTS; $attempt++) {
            $candidate = $this->numberGenerator->generate();

            if (! $this->accounts->numberExists($candidate)) {
                return $candidate;
            }
        }

        // A bounded retry protects workers if a generator becomes unhealthy.
        throw AccountNumberGenerationFailed::create();
    }
}
