<?php

declare(strict_types=1);

namespace Notification\Domain\Email\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** A validated destination address for an outgoing notification email. */
final class RecipientEmail extends ValueObject
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = mb_strtolower(trim($value));

        if (filter_var($this->value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($this->value) > 254) {
            throw new InvalidArgumentException('A valid recipient email address is required.');
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
