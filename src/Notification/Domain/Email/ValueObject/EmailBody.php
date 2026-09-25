<?php

declare(strict_types=1);

namespace Notification\Domain\Email\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Plain-text email content that remains independent of any template engine. */
final class EmailBody extends ValueObject
{
    public function __construct(private readonly string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('An email body cannot be empty.');
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
