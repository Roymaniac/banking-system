<?php

declare(strict_types=1);

namespace Administration\Domain\Staff\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** The employee's readable role title, separate from access permissions. */
final class JobTitle extends ValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if (mb_strlen($normalized) < 2 || mb_strlen($normalized) > 100) {
            throw new InvalidArgumentException('A job title must contain between 2 and 100 characters.');
        }

        $this->value = $normalized;
    }

    protected function toArray(): array
    {
        return ['value' => $this->value];
    }
}
