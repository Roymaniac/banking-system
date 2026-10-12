<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

use DateInterval;
use Identity\Application\Authentication\PasswordHasher;
use Identity\Application\Security\SecurityMonitor;
use Identity\Application\Security\UserSessionRevoker;
use Identity\Domain\Authentication\Exception\InvalidCredentials;
use Identity\Domain\Authentication\Exception\InvalidMultiFactorCode;
use Identity\Domain\Authentication\Exception\MultiFactorAlreadyEnabled;
use Identity\Domain\User\Repository\UserRepository;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Contracts\Clock;
use Shared\Contracts\TransactionManager;

/** Coordinates enrollment and two-step login without exposing stored secrets. */
final readonly class MultiFactorAuthentication
{
    private const CHALLENGE_MINUTES = 5;

    private const MAX_CHALLENGE_ATTEMPTS = 5;

    public function __construct(
        private MultiFactorGateway $gateway,
        private TotpAuthenticator $totp,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private UserSessionRevoker $sessions,
        private SecurityMonitor $security,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function isEnabled(UserId $userId): bool
    {
        return $this->gateway->isEnabled($userId);
    }

    public function beginSetup(UserId $userId, string $currentPassword): MultiFactorSetup
    {
        if ($this->gateway->isEnabled($userId)) {
            throw MultiFactorAlreadyEnabled::create();
        }

        $user = $this->users->findById($userId);

        if ($user === null || ! $this->passwordHasher->verify($currentPassword, $user->passwordHash())) {
            throw InvalidCredentials::create();
        }

        $secret = $this->totp->generateSecret();
        $this->gateway->replacePendingSetup($userId, $secret, $this->clock->now());

        return new MultiFactorSetup(
            $secret,
            $this->totp->provisioningUri(
                $user->email(),
                (string) config('app.name', 'Banking System'),
                $secret,
            ),
        );
    }

    /** @return list<string> */
    public function confirmSetup(UserId $userId, string $code, int $currentSessionId): array
    {
        $secret = $this->gateway->secret($userId, false);

        if ($secret === null || ! $this->totp->verify($secret, $code, $this->clock->now())) {
            throw InvalidMultiFactorCode::create();
        }

        $recoveryCodes = $this->recoveryCodes();
        $hashes = $this->hashRecoveryCodes($recoveryCodes);

        $this->transactions->run(function () use ($userId, $hashes, $currentSessionId): void {
            $this->gateway->confirm($userId, $hashes, $this->clock->now());

            // Sessions created before MFA was enabled never passed a second
            // factor, so only the device completing enrollment may remain.
            $this->sessions->revokeAllExcept($userId, $currentSessionId);
        });

        return $recoveryCodes;
    }

    public function disable(UserId $userId, string $currentPassword, string $code): void
    {
        $user = $this->users->findById($userId);
        $secret = $this->gateway->secret($userId);

        if ($user === null || ! $this->passwordHasher->verify($currentPassword, $user->passwordHash())) {
            throw InvalidCredentials::create();
        }

        if ($secret === null || ! $this->totp->verify($secret, $code, $this->clock->now())) {
            throw InvalidMultiFactorCode::create();
        }

        $this->gateway->disable($userId);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(
        UserId $userId,
        string $currentPassword,
        string $code,
    ): array {
        $user = $this->users->findById($userId);
        $secret = $this->gateway->secret($userId);

        if ($user === null || ! $this->passwordHasher->verify($currentPassword, $user->passwordHash())) {
            throw InvalidCredentials::create();
        }

        if ($secret === null || ! $this->totp->verify($secret, $code, $this->clock->now())) {
            throw InvalidMultiFactorCode::create();
        }

        $recoveryCodes = $this->recoveryCodes();
        $this->gateway->replaceRecoveryCodes(
            $userId,
            $this->hashRecoveryCodes($recoveryCodes),
            $this->clock->now(),
        );

        return $recoveryCodes;
    }

    /** @return array{token: string, expires_at: \DateTimeImmutable} */
    public function beginLogin(UserId $userId, string $deviceName): array
    {
        return $this->beginChallenge($userId, $deviceName, MultiFactorChallengePurpose::Login);
    }

    /** @return array{token: string, expires_at: \DateTimeImmutable} */
    public function beginPrivilegedAccess(UserId $userId, string $deviceName): array
    {
        return $this->beginChallenge(
            $userId,
            $deviceName,
            MultiFactorChallengePurpose::PrivilegedAccess,
        );
    }

    /** @return array{token: string, expires_at: \DateTimeImmutable} */
    public function beginHighValueTransfer(UserId $userId, string $contextHash): array
    {
        return $this->beginChallenge(
            $userId,
            'High-value transfer approval',
            MultiFactorChallengePurpose::HighValueTransfer,
            $contextHash,
        );
    }

    /** @return array{token: string, expires_at: \DateTimeImmutable} */
    private function beginChallenge(
        UserId $userId,
        string $deviceName,
        MultiFactorChallengePurpose $purpose,
        ?string $contextHash = null,
    ): array {
        $token = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $expiresAt = $now->add(new DateInterval('PT'.self::CHALLENGE_MINUTES.'M'));

        $this->gateway->storeChallenge(
            hash('sha256', $token),
            $userId,
            $deviceName,
            $purpose,
            $contextHash,
            $expiresAt,
            $now,
        );

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function completeLogin(string $token, string $code): CompletedMultiFactorLogin
    {
        $tokenHash = hash('sha256', $token);

        $completed = $this->transactions->run(function () use ($tokenHash, $code): ?CompletedMultiFactorLogin {
            $challenge = $this->gateway->challenge($tokenHash);
            $now = $this->clock->now();

            if (
                $challenge === null
                || $challenge->expiresAt <= $now
                || $challenge->attempts >= self::MAX_CHALLENGE_ATTEMPTS
            ) {
                $this->security->multiFactorChallengeFailed($challenge?->userId);

                return null;
            }

            $secret = $this->gateway->secret($challenge->userId);
            $validTotp = $secret !== null && $this->totp->verify($secret, $code, $now);
            $validRecoveryCode = ! $validTotp
                && $this->gateway->consumeRecoveryCode($challenge->userId, $code);

            if (! $validTotp && ! $validRecoveryCode) {
                $this->gateway->recordFailedChallengeAttempt($tokenHash);
                $this->security->multiFactorChallengeFailed($challenge->userId);

                return null;
            }

            $this->gateway->consumeChallenge($tokenHash);

            return new CompletedMultiFactorLogin(
                $challenge->userId,
                $challenge->deviceName,
                $challenge->purpose,
                $challenge->contextHash,
            );
        });

        if ($completed === null) {
            throw InvalidMultiFactorCode::create();
        }

        return $completed;
    }

    /** @return list<string> */
    private function recoveryCodes(): array
    {
        $codes = [];

        for ($index = 0; $index < 10; $index++) {
            $value = strtoupper(bin2hex(random_bytes(6)));
            $codes[] = substr($value, 0, 4).'-'.substr($value, 4, 4).'-'.substr($value, 8, 4);
        }

        return $codes;
    }

    /**
     * @param  list<string>  $recoveryCodes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $recoveryCodes): array
    {
        return array_map(
            static fn (string $recoveryCode): string => hash('sha256', $recoveryCode),
            $recoveryCodes,
        );
    }
}
