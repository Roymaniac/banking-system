<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Transaction\Application\Control\MoneyMovementControl;

/** Lets an operator stop financial writes while keeping diagnostics available. */
final class SuspendMoneyMovementCommand extends Command
{
    protected $signature = 'banking:money-movement:suspend {reason : Auditable reason for the suspension}';

    protected $description = 'Suspend all deposits, withdrawals, transfers, and reversals';

    public function handle(MoneyMovementControl $control): int
    {
        try {
            $control->suspend((string) $this->argument('reason'), 'operator_cli');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('Money movement is suspended. Read-only and diagnostic operations remain available.');

        return self::SUCCESS;
    }
}
