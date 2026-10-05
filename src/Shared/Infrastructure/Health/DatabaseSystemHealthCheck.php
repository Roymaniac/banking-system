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
