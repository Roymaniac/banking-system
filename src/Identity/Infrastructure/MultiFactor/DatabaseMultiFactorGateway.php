<?php

declare(strict_types=1);

namespace Identity\Infrastructure\MultiFactor;

use DateTimeImmutable;
use DateTimeZone;
use Identity\Application\MultiFactor\MultiFactorChallengePurpose;
use Identity\Application\MultiFactor\MultiFactorGateway;
use Identity\Application\MultiFactor\MultiFactorLoginChallenge;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;

/** Encrypts authenticator secrets and stores only hashes of recovery codes. */
final readonly class DatabaseMultiFactorGateway implements MultiFactorGateway
{
    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
    ) {}

    public function isEnabled(UserId $userId): bool
    {
        return $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value())
            ->whereNotNull('confirmed_at')
            ->exists();
    }

    public function replacePendingSetup(UserId $userId, string $secret, DateTimeImmutable $createdAt): void
    {
        $this->connection->table('user_multi_factor_authentication')->updateOrInsert(
            ['user_id' => $userId->value()],
            [
                'encrypted_secret' => $this->encrypter->encrypt($secret, false),
                'recovery_code_hashes' => null,
                'confirmed_at' => null,
                'created_at' => $createdAt->setTimezone(new DateTimeZone('UTC')),
                'updated_at' => $createdAt->setTimezone(new DateTimeZone('UTC')),
            ],
        );
    }

    public function secret(UserId $userId, bool $confirmedOnly = true): ?string
    {
        $query = $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value());

        if ($confirmedOnly) {
            $query->whereNotNull('confirmed_at');
        }

        $encrypted = $query->value('encrypted_secret');

        if (! is_string($encrypted)) {
            return null;
        }

        $secret = $this->encrypter->decrypt($encrypted, false);

        return is_string($secret) ? $secret : null;
    }

    public function confirm(UserId $userId, array $recoveryCodeHashes, DateTimeImmutable $confirmedAt): void
    {
        $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value())
            ->update([
                'recovery_code_hashes' => json_encode($recoveryCodeHashes, JSON_THROW_ON_ERROR),
                'confirmed_at' => $confirmedAt->setTimezone(new DateTimeZone('UTC')),
                'updated_at' => $confirmedAt->setTimezone(new DateTimeZone('UTC')),
            ]);
    }

    public function disable(UserId $userId): void
    {
        $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value())
            ->delete();
    }

    public function replaceRecoveryCodes(
        UserId $userId,
        array $recoveryCodeHashes,
        DateTimeImmutable $replacedAt,
    ): void {
        $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value())
            ->whereNotNull('confirmed_at')
            ->update([
                'recovery_code_hashes' => json_encode($recoveryCodeHashes, JSON_THROW_ON_ERROR),
                'updated_at' => $replacedAt->setTimezone(new DateTimeZone('UTC')),
            ]);
    }

    public function consumeRecoveryCode(UserId $userId, string $code): bool
    {
        $record = $this->connection->table('user_multi_factor_authentication')
            ->where('user_id', $userId->value())
            ->whereNotNull('confirmed_at')
            ->lockForUpdate()
            ->first();

        if ($record === null || ! is_string($record->recovery_code_hashes)) {
            return false;
        }

        $hashes = json_decode($record->recovery_code_hashes, true, 512, JSON_THROW_ON_ERROR);
        $candidate = hash('sha256', strtoupper(trim($code)));

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && hash_equals($hash, $candidate)) {
                unset($hashes[$index]);
                $this->connection->table('user_multi_factor_authentication')
                    ->where('user_id', $userId->value())
                    ->update([
                        'recovery_code_hashes' => json_encode(array_values($hashes), JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);

                return true;
            }
        }

        return false;
    }

    public function storeChallenge(
        string $tokenHash,
        UserId $userId,
        string $deviceName,
        MultiFactorChallengePurpose $purpose,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): void {
        $this->connection->table('multi_factor_login_challenges')->insert([
            'token_hash' => $tokenHash,
            'user_id' => $userId->value(),
            'device_name' => $deviceName,
            'purpose' => $purpose->value,
            'attempts' => 0,
            'expires_at' => $expiresAt->setTimezone(new DateTimeZone('UTC')),
            'created_at' => $createdAt->setTimezone(new DateTimeZone('UTC')),
        ]);
    }

    public function challenge(string $tokenHash): ?MultiFactorLoginChallenge
    {
        $record = $this->connection->table('multi_factor_login_challenges')
            ->where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            return null;
        }

        return new MultiFactorLoginChallenge(
            new UserId((string) $record->user_id),
            (string) $record->device_name,
            MultiFactorChallengePurpose::from((string) $record->purpose),
            (int) $record->attempts,
            new DateTimeImmutable((string) $record->expires_at),
        );
    }

    public function recordFailedChallengeAttempt(string $tokenHash): void
    {
        $this->connection->table('multi_factor_login_challenges')
            ->where('token_hash', $tokenHash)
            ->increment('attempts');
    }

    public function consumeChallenge(string $tokenHash): void
    {
        $this->connection->table('multi_factor_login_challenges')
            ->where('token_hash', $tokenHash)
            ->delete();
    }
}
