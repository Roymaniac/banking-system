<?php

declare(strict_types=1);

namespace Transaction\Application\DailyLimit;

use Account\Domain\Account\Repository\AccountRepository;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Exception\AccountNotEligibleForDailyLimit;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;

/** Creates or replaces an account's daily outgoing transaction limit. */
final readonly class ConfigureDailyTransactionLimit
{
    public function __construct(
        private AccountRepository $accounts,
        private DailyTransactionLimitRepository $limits,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ConfigureDailyTransactionLimitCommand $command): DailyTransactionLimit
    {
        $account = $this->accounts->findById($command->accountId);

        if ($account === null) {
            throw AccountNotEligibleForDailyLimit::create();
        }

        $previousVersion = $this->limits->find($account->id())?->version() ?? 0;

        $limit = DailyTransactionLimit::configure(
            $account->id(),
            new LedgerCurrency($account->currency()->value()),
            $command->maximumMinorUnits,
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId,
            $previousVersion
        );

        $events = $this->transactions->run(function () use ($limit): array {
            $this->limits->save($limit);

            return $limit->pullDomainEvents();
        });
        $this->events->publish($events);

        return $limit;
    }
}
