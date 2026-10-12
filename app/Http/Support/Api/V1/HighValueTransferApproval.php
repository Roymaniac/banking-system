<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use Account\Domain\Account\ValueObject\AccountId;
use Identity\Domain\User\ValueObject\UserId;

/** Decides when MFA is needed and binds approval to one exact transfer. */
final class HighValueTransferApproval
{
    private const ABILITY_PREFIX = 'high-value-transfer:';

    public function isRequired(int $minorUnits): bool
    {
        $threshold = max(1, (int) config('security.high_value_transfer.threshold_minor_units'));

        return $minorUnits >= $threshold;
    }

    public function fingerprint(
        UserId $userId,
        AccountId $senderId,
        AccountId $recipientId,
        int $minorUnits,
        string $reference,
        string $idempotencyKey,
    ): string {
        return hash('sha256', implode('|', [
            $userId->value(),
            $senderId->value(),
            $recipientId->value(),
            (string) $minorUnits,
            strtoupper($reference),
            $idempotencyKey,
        ]));
    }

    /** @param list<array{account_id: string, minor_units: int}> $recipients */
    public function batchFingerprint(
        UserId $userId,
        AccountId $senderId,
        array $recipients,
        string $reference,
        string $idempotencyKey,
    ): string {
        $instructions = array_map(
            static fn (array $recipient): string => $recipient['account_id'].':'.$recipient['minor_units'],
            $recipients,
        );

        return hash('sha256', implode('|', [
            $userId->value(),
            $senderId->value(),
            implode(',', $instructions),
            strtoupper($reference),
            $idempotencyKey,
        ]));
    }

    public function ability(string $fingerprint): string
    {
        return self::ABILITY_PREFIX.$fingerprint;
    }
}
