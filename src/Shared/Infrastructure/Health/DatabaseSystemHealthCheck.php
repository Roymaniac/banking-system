<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Health;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Shared\Application\Health\SystemHealthCheck;
use Shared\Application\Health\SystemHealthReport;
use Shared\Contracts\Clock;
use Throwable;

/** Checks critical services using safe counts and freshness signals. */
final readonly class DatabaseSystemHealthCheck implements SystemHealthCheck
{
    private const SCHEDULER_MAX_AGE_SECONDS = 120;

    private const RECONCILIATION_MAX_AGE_SECONDS = 7200;

    public function __construct(
        private ConnectionInterface $connection,
        private ConfigRepository $config,
        private Clock $clock,
    ) {}

    public function inspect(): SystemHealthReport
    {
        try {
            $this->connection->select('SELECT 1');
            $components = [
                'database' => ['status' => 'healthy'],
                'scheduler' => $this->schedulerHealth(),
                'ledger_reconciliation' => $this->reconciliationHealth(),
                'money_movement' => $this->moneyMovementHealth(),
                'queue' => $this->queueHealth(),
                'notifications' => $this->notificationHealth(),
            ];
        } catch (Throwable) {
            // Never return exception text because it may expose hosts or credentials.
            return new SystemHealthReport('unhealthy', [
                'database' => ['status' => 'unhealthy'],
            ]);
        }

        $statuses = array_column($components, 'status');
        $status = in_array('unhealthy', $statuses, true)
            ? 'unhealthy'
            : (in_array('degraded', $statuses, true) ? 'degraded' : 'healthy');

        return new SystemHealthReport($status, $components);
    }

    /** @return array<string, bool|int|string|null> */
    private function reconciliationHealth(): array
    {
        $status = $this->connection->table('ledger_reconciliation_statuses')
            ->where('name', 'ledger')
            ->first();

        if ($status === null) {
            return ['status' => 'unhealthy', 'last_checked_at' => null, 'age_seconds' => null];
        }

        $checkedAt = new DateTimeImmutable((string) $status->checked_at);
        $age = max(0, $this->clock->now()->getTimestamp() - $checkedAt->getTimestamp());
        $healthy = $status->status === 'healthy' && $age <= self::RECONCILIATION_MAX_AGE_SECONDS;

        return [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'last_checked_at' => $checkedAt->format(DATE_ATOM),
            'age_seconds' => $age,
            'unbalanced_posted_entries' => (int) $status->unbalanced_posted_entries,
            'contribution_mismatches' => (int) $status->contribution_mismatches,
            'balance_mismatches' => (int) $status->balance_mismatches,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function moneyMovementHealth(): array
    {
        $control = $this->connection->table('money_movement_controls')
            ->where('name', 'global')
            ->first();

        if ($control === null) {
            return ['status' => 'unhealthy', 'enabled' => false, 'changed_at' => null];
        }

        $enabled = filter_var($control->enabled, FILTER_VALIDATE_BOOL);

        return [
            'status' => $enabled ? 'healthy' : 'degraded',
            'enabled' => $enabled,
            'changed_at' => (new DateTimeImmutable((string) $control->changed_at))->format(DATE_ATOM),
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function schedulerHealth(): array
    {
        $recordedAt = $this->connection->table('system_heartbeats')
            ->where('name', 'scheduler')
            ->value('recorded_at');

        if ($recordedAt === null) {
            return ['status' => 'unhealthy', 'last_seen_at' => null, 'age_seconds' => null];
        }

        $lastSeen = new DateTimeImmutable((string) $recordedAt);
        $age = max(0, $this->clock->now()->getTimestamp() - $lastSeen->getTimestamp());

        return [
            'status' => $age <= self::SCHEDULER_MAX_AGE_SECONDS ? 'healthy' : 'unhealthy',
            'last_seen_at' => $lastSeen->format(DATE_ATOM),
            'age_seconds' => $age,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function queueHealth(): array
    {
        $driver = (string) $this->config->get('queue.default', 'unknown');
        $pending = $this->connection->table('jobs')->count();
        $failed = $this->connection->table('failed_jobs')->count();

        return [
            'status' => $failed > 0 || in_array($driver, ['sync', 'null'], true) ? 'degraded' : 'healthy',
            'driver' => $driver,
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    private function notificationHealth(): array
    {
        $pending = $this->connection->table('email_outbox')
            ->whereNull('delivered_at')
            ->where('attempts', '<', 5)
            ->count();
        $exhausted = $this->connection->table('email_outbox')
            ->whereNull('delivered_at')
            ->where('attempts', '>=', 5)
            ->count();

        return [
            'status' => $exhausted > 0 ? 'degraded' : 'healthy',
            'pending_messages' => $pending,
            'exhausted_messages' => $exhausted,
        ];
    }
}
