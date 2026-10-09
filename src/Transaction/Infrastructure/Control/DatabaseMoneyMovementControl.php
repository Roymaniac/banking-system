<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Transaction\Application\Control\Exception\MoneyMovementSuspended;
use Transaction\Application\Control\MoneyMovementControl;

/** Coordinates the global safety switch with in-flight financial transactions. */
final readonly class DatabaseMoneyMovementControl implements MoneyMovementControl
{
    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function assertEnabled(): void
    {
        $control = $this->connection->table('money_movement_controls')
            ->where('name', 'global')
            ->lockForUpdate()
            ->first();

        if ($control === null || ! filter_var($control->enabled, FILTER_VALIDATE_BOOL)) {
            throw MoneyMovementSuspended::create();
        }
    }

    public function suspend(string $reason, string $source): void
    {
        $this->change(false, 'suspended', $reason, $source);
    }

    public function resume(string $reason, string $source): void
    {
        $this->change(true, 'resumed', $reason, $source);
    }

    private function change(bool $enabled, string $action, string $reason, string $source): void
    {
        $reason = trim($reason);
        $source = trim($source);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('The operational reason must contain between 10 and 255 characters.');
        }

        if ($source === '' || mb_strlen($source) > 50) {
            throw new InvalidArgumentException('The operational source must contain between 1 and 50 characters.');
        }

        $this->connection->transaction(function () use ($enabled, $action, $reason, $source): void {
            $control = $this->connection->table('money_movement_controls')
                ->where('name', 'global')
                ->lockForUpdate()
                ->first();

            if ($control !== null && filter_var($control->enabled, FILTER_VALIDATE_BOOL) === $enabled) {
                return;
            }

            $occurredAt = $this->utcNow();
            $this->connection->table('money_movement_controls')->updateOrInsert(
                ['name' => 'global'],
                [
                    'enabled' => $enabled,
                    'reason' => $enabled ? null : $reason,
                    'source' => $source,
                    'changed_at' => $occurredAt,
                ],
            );

            $this->connection->table('money_movement_control_events')->insert([
                'action' => $action,
                'reason' => $reason,
                'source' => $source,
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    private function utcNow(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
