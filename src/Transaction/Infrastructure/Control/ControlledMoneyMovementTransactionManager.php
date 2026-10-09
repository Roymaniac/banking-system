<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use Shared\Contracts\TransactionManager;
use Shared\Infrastructure\Persistence\LaravelTransactionManager;
use Transaction\Application\Control\MoneyMovementControl;

/** Places the global safety check inside every money-changing transaction. */
final readonly class ControlledMoneyMovementTransactionManager implements TransactionManager
{
    public function __construct(
        private LaravelTransactionManager $transactions,
        private MoneyMovementControl $control,
    ) {}

    public function run(callable $callback): mixed
    {
        return $this->transactions->run(function () use ($callback): mixed {
            $this->control->assertEnabled();

            return $callback();
        });
    }
}
