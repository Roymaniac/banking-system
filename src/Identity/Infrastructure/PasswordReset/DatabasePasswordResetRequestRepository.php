<?php

declare(strict_types=1);

namespace Identity\Infrastructure\PasswordReset;

use DateTimeImmutable;
use Identity\Domain\PasswordReset\PasswordResetRequest;
use Identity\Domain\PasswordReset\Repository\PasswordResetRequestRepository;
use Identity\Domain\PasswordReset\ValueObject\PasswordResetTokenHash;
use Identity\Domain\User\ValueObject\EmailAddress;
use Illuminate\Database\ConnectionInterface;

/**
 * Persists only reset-token hashes; the raw secret never reaches the database.
 */
final readonly class DatabasePasswordResetRequestRepository implements PasswordResetRequestRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function replace(PasswordResetRequest $request): void
    {
        $this->connection->table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email()->value()],
            [
                'token' => $request->tokenHash()->value(),
                'created_at' => $request->requestedAt(),
                'expires_at' => $request->expiresAt(),
            ],
        );
    }

    public function findByTokenHash(PasswordResetTokenHash $tokenHash): ?PasswordResetRequest
    {
        $record = $this->connection
            ->table('password_reset_tokens')
            ->where('token', $tokenHash->value())
            ->whereNotNull('created_at')
            ->whereNotNull('expires_at')
            ->first();

        if ($record === null) {
            return null;
        }

        return new PasswordResetRequest(
            email: new EmailAddress($record->email),
            tokenHash: new PasswordResetTokenHash($record->token),
            requestedAt: new DateTimeImmutable($record->created_at),
            expiresAt: new DateTimeImmutable($record->expires_at),
        );
    }

    public function consume(PasswordResetTokenHash $tokenHash): void
    {
        $this->connection
            ->table('password_reset_tokens')
            ->where('token', $tokenHash->value())
            ->delete();
    }
}
