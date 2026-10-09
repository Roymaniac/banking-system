<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Control;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\Exception\MoneyMovementSuspended;
use Transaction\Application\Control\MoneyMovementControl;
use Transaction\Application\Control\MoneyMovementStatus;

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

    public function suspend(string $reason, string $source, ?Uuid $actorUserId = null): void
    {
        $this->change(false, 'suspended', $reason, $source, $actorUserId);
    }

    public function resume(string $reason, string $source, ?Uuid $actorUserId = null): void
    {
        $this->change(true, 'resumed', $reason, $source, $actorUserId);
    }

    public function current(): MoneyMovementStatus
    {
        $control = $this->connection->table('money_movement_controls')
            ->where('name', 'global')
            ->first();

        if ($control === null) {
            throw MoneyMovementSuspended::create();
        }

        return new MoneyMovementStatus(
            filter_var($control->enabled, FILTER_VALIDATE_BOOL),
            $control->reason === null ? null : (string) $control->reason,
            (string) $control->source,
            new DateTimeImmutable((string) $control->changed_at, new DateTimeZone('UTC')),
        );
    }

    private function change(
        bool $enabled,
        string $action,
        string $reason,
        string $source,
        ?Uuid $actorUserId,
    ): void {
        $reason = trim($reason);
        $source = trim($source);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('The operational reason must contain between 10 and 255 characters.');
        }

        if ($source === '' || mb_strlen($source) > 50) {
            throw new InvalidArgumentException('The operational source must contain between 1 and 50 characters.');
        }

        $this->connection->transaction(function () use ($enabled, $action, $reason, $source, $actorUserId): void {
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
                'actor_user_id' => $actorUserId?->value(),
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    private function utcNow(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
