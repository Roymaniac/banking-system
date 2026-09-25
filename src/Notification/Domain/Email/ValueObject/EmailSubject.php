<?php

declare(strict_types=1);

namespace Notification\Domain\Email\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** A short, single-line subject safe to pass to an email transport. */
final class EmailSubject extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim($value);

        if ($this->value === '' || mb_strlen($this->value) > 200 || str_contains($this->value, "\r") || str_contains($this->value, "\n")) {
            throw new InvalidArgumentException('An email subject must be a non-empty single line of at most 200 characters.');
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
