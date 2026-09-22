<?php

declare(strict_types=1);

namespace Transaction\Domain\Reversal\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Explains why an earlier financial entry had to be cancelled. */
final class ReversalReason extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);

        if ($this->value === '' || mb_strlen($this->value) > 255) {
            throw new InvalidArgumentException('A reversal reason must contain between 1 and 255 characters.');
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
