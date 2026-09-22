<?php

declare(strict_types=1);

namespace Transaction\Domain\Common\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Provides a stable idempotency reference supplied by the transaction source. */
final class TransactionReference extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtoupper(trim($value));

        if (preg_match('/^[A-Z0-9][A-Z0-9._:\/-]{0,99}$/', $this->value) !== 1) {
            throw new InvalidArgumentException('A transaction reference must be 1 to 100 letters, numbers, or safe separators.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
