<?php

declare(strict_types=1);

namespace App\Support\Banking;

use InvalidArgumentException;
use Ledger\Domain\Ledger\ValueObject\LedgerId;

/** Resolves trusted settlement ledgers from server configuration. */
final class ConfiguredSettlementLedgers
{
    public function find(string $operation, string $currency): ?LedgerId
    {
        $value = config("banking.settlement_ledgers.{$operation}.".strtoupper($currency));

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new LedgerId($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
