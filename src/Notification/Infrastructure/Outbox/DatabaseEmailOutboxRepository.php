<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Outbox;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Notification\Domain\Email\EmailMessage;
use Notification\Domain\Email\ValueObject\EmailBody;
use Notification\Domain\Email\ValueObject\EmailSubject;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Notification\Domain\Outbox\EmailOutboxMessage;
use Notification\Domain\Outbox\Repository\EmailOutboxRepository;
use Shared\Domain\Identifier\Uuid;

/** Keeps recipients and email contents encrypted while they wait in the database. */
final readonly class DatabaseEmailOutboxRepository implements EmailOutboxRepository
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
    ) {}

    public function add(EmailOutboxMessage $message): void
    {
        $payload = json_encode([
            'recipient' => $message->email()->recipient()->value(),
            'subject' => $message->email()->subject()->value(),
            'body' => $message->email()->body()->value(),
        ], JSON_THROW_ON_ERROR);

        $this->connection->table('email_outbox')->insert([
            'id' => $message->id()->value(),
            'encrypted_payload' => $this->encrypter->encryptString($payload),
            'attempts' => $message->attempts(),
            'recorded_at' => $message->recordedAt()->setTimezone(new DateTimeZone('UTC')),
            'last_attempted_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function pending(int $limit): array
    {
        return $this->connection->table('email_outbox')
            ->whereNull('delivered_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('recorded_at')
            ->limit($limit)
            ->get()
            ->map(fn(object $record): EmailOutboxMessage => $this->hydrate($record))
            ->all();
    }

    public function markDelivered(Uuid $id, DateTimeImmutable $deliveredAt): void
    {
        $this->connection->table('email_outbox')
            ->where('id', $id->value())
            ->whereNull('delivered_at')
            ->update([
                'attempts' => $this->connection->raw('attempts + 1'),
                'last_attempted_at' => $deliveredAt->setTimezone(new DateTimeZone('UTC')),
                'delivered_at' => $deliveredAt->setTimezone(new DateTimeZone('UTC')),
            ]);
    }

    public function recordFailure(Uuid $id, DateTimeImmutable $failedAt): void
    {
        // Do not persist exception text because transport errors may contain credentials.
        $this->connection->table('email_outbox')
            ->where('id', $id->value())
            ->whereNull('delivered_at')
            ->increment(
                'attempts',
                1,
                [
                    'last_attempted_at' => $failedAt->setTimezone(new DateTimeZone('UTC')),
                ]
            );
    }

    private function hydrate(object $record): EmailOutboxMessage
    {
        /** @var array{recipient: string, subject: string, body: string} $payload */
        $payload = json_decode(
            $this->encrypter->decryptString($record->encrypted_payload),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return new EmailOutboxMessage(
            new Uuid($record->id),
            new EmailMessage(
                new RecipientEmail($payload['recipient']),
                new EmailSubject($payload['subject']),
                new EmailBody($payload['body'])
            ),
            new DateTimeImmutable($record->recorded_at),
            (int) $record->attempts,
        );
    }
}
