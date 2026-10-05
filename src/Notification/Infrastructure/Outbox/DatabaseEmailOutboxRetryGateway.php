<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Outbox;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Notification\Application\Outbox\EmailOutboxRetryGateway;
use Notification\Application\Outbox\EmailOutboxRetryResult;
use Shared\Domain\Identifier\Uuid;

/** Uses a row lock so two operators cannot requeue the same message concurrently. */
final readonly class DatabaseEmailOutboxRetryGateway implements EmailOutboxRetryGateway
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private ConnectionInterface $connection) {}

    public function retry(Uuid $id, DateTimeImmutable $requeuedAt): EmailOutboxRetryResult
    {
        return $this->connection->transaction(function () use ($id, $requeuedAt): EmailOutboxRetryResult {
            $message = $this->connection->table('email_outbox')
                ->where('id', $id->value())
                ->lockForUpdate()
                ->first(['attempts', 'delivered_at']);

            if ($message === null) {
                return EmailOutboxRetryResult::NotFound;
            }
            if ($message->delivered_at !== null) {
                return EmailOutboxRetryResult::Delivered;
            }
            if ((int) $message->attempts < self::MAX_ATTEMPTS) {
                return EmailOutboxRetryResult::StillPending;
            }

            $this->connection->table('email_outbox')
                ->where('id', $id->value())
                ->update([
                    'attempts' => 0,
                    'last_attempted_at' => null,
                    'retry_cycles' => $this->connection->raw('retry_cycles + 1'),
                    'requeued_at' => $requeuedAt->setTimezone(new DateTimeZone('UTC')),
                ]);

            return EmailOutboxRetryResult::Requeued;
        });
    }
}
