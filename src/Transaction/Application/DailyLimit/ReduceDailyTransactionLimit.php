<?php

declare(strict_types=1);

namespace Transaction\Application\DailyLimit;

use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Identifier\UuidGenerator;
use Transaction\Domain\DailyLimit\DailyTransactionLimit;
use Transaction\Domain\DailyLimit\Exception\DailyTransactionLimitNotConfigured;
use Transaction\Domain\DailyLimit\Repository\DailyTransactionLimitRepository;

/** Lowers a customer limit while preserving the bank-controlled maximum. */
final readonly class ReduceDailyTransactionLimit
{
    public function __construct(
        private DailyTransactionLimitRepository $limits,
        private Clock $clock,
        private UuidGenerator $uuidGenerator,
        private TransactionManager $transactions,
        private EventPublisher $events,
    ) {}

    public function handle(ReduceDailyTransactionLimitCommand $command): DailyTransactionLimit
    {
        $limit = $this->limits->find($command->accountId)
            ?? throw DailyTransactionLimitNotConfigured::create();

        $limit->reduce(
            $command->maximumMinorUnits,
            $this->clock->now(),
            $this->uuidGenerator->generate(),
            $command->correlationId,
        );

        $events = $this->transactions->run(function () use ($limit): array {
            $this->limits->save($limit);

            return $limit->pullDomainEvents();
        });

        $this->events->publish($events);

        return $limit;
    }
}
