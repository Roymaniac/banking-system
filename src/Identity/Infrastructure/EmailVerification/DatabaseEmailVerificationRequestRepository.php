<?php

declare(strict_types=1);

namespace Identity\Infrastructure\EmailVerification;

use DateTimeImmutable;
use Identity\Domain\EmailVerification\EmailVerificationRequest;
use Identity\Domain\EmailVerification\Repository\EmailVerificationRequestRepository;
use Identity\Domain\EmailVerification\ValueObject\EmailVerificationTokenHash;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Database\ConnectionInterface;

/**
 * Persists only verification-token hashes; raw tokens never reach storage.
 */
final readonly class DatabaseEmailVerificationRequestRepository implements EmailVerificationRequestRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function replace(EmailVerificationRequest $request): void
    {
        $this->connection->table('email_verification_tokens')->updateOrInsert(
            ['user_id' => $request->userId()->value()],
            [
                'token' => $request->tokenHash()->value(),
                'created_at' => $request->requestedAt(),
                'expires_at' => $request->expiresAt(),
            ],
        );
    }

    public function findByTokenHash(EmailVerificationTokenHash $tokenHash): ?EmailVerificationRequest
    {
        $record = $this->connection
            ->table('email_verification_tokens')
            ->where('token', $tokenHash->value())
            ->first();

        if ($record === null) {
            return null;
        }

        return new EmailVerificationRequest(
            userId: new UserId($record->user_id),
            tokenHash: new EmailVerificationTokenHash($record->token),
            requestedAt: new DateTimeImmutable($record->created_at),
            expiresAt: new DateTimeImmutable($record->expires_at),
        );
    }

    public function consume(EmailVerificationTokenHash $tokenHash): void
    {
        $this->connection
            ->table('email_verification_tokens')
            ->where('token', $tokenHash->value())
            ->delete();
    }
}
