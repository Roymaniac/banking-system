<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Transaction\Application\Control\MoneyMovementControl;

/** Restores financial writes after an operator completes incident checks. */
final class ResumeMoneyMovementCommand extends Command
{
    protected $signature = 'banking:money-movement:resume {reason : Auditable reason for restoring service}';

    protected $description = 'Resume deposits, withdrawals, transfers, and reversals';

    public function handle(MoneyMovementControl $control): int
    {
        try {
            $control->resume((string) $this->argument('reason'), 'operator_cli');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Money movement is enabled.');

        return self::SUCCESS;
    }
}
