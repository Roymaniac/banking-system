<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Transaction\Application\Control\BreakGlassMoneyMovementResume;
use Transaction\Application\Control\Exception\InvalidMoneyMovementResume;
use Transaction\Application\Control\MoneyMovementControl;

/** Restores financial writes after an operator completes incident checks. */
final class ResumeMoneyMovementCommand extends Command
{
    protected $signature = 'banking:money-movement:resume
        {reason : Auditable reason for restoring service}
        {--break-glass : Use the exceptional production recovery path}
        {--operator= : UUID of the authorized emergency operator}
        {--incident= : External incident or change-management reference}
        {--yes : Confirm the emergency action without an interactive prompt}';

    protected $description = 'Resume money movement outside production or invoke the audited production break-glass path';

    public function handle(
        Application $application,
        MoneyMovementControl $control,
        BreakGlassMoneyMovementResume $breakGlassResume,
    ): int {
        if ($application->environment('production') && ! $this->option('break-glass')) {
            $this->error('Direct production resumption is disabled. Use the protected API approval workflow.');

            return self::FAILURE;
        }

        try {
            if ($this->option('break-glass')) {
                return $this->breakGlass($breakGlassResume);
            }

            $control->resume((string) $this->argument('reason'), 'operator_cli');
        } catch (InvalidArgumentException|InvalidMoneyMovementResume $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Money movement is enabled.');

        return self::SUCCESS;
    }

    private function breakGlass(BreakGlassMoneyMovementResume $resume): int
    {
        $operator = trim((string) $this->option('operator'));
        $incident = trim((string) $this->option('incident'));

        if ($operator === '' || $incident === '') {
            $this->error('Break-glass resumption requires both --operator and --incident.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm(
            'This bypasses normal two-person approval. Confirm emergency resumption?',
        )) {
            $this->warn('Break-glass resumption cancelled. Money movement remains suspended.');

            return self::FAILURE;
        }

        try {
            $resume->handle(
                new UserId($operator),
                $incident,
                (string) $this->argument('reason'),
            );
        } catch (InvalidArgumentException|InvalidMoneyMovementResume $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn('BREAK-GLASS USED: money movement is enabled and a critical security event was recorded.');

        return self::SUCCESS;
    }
}
