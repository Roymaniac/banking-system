<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Contact\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Validates and normalizes an email address or international phone number. */
final class ContactPoint extends ValueObject
{
    private string $value;

    public function __construct(private readonly ContactType $type, string $value)
    {
        $this->value = match ($type) {
            ContactType::Email => strtolower(trim($value)),
            ContactType::Phone => preg_replace('/[\s()-]+/', '', trim($value)) ?? trim($value),
        };

        $valid = match ($type) {
            ContactType::Email => filter_var($this->value, FILTER_VALIDATE_EMAIL) !== false,
            // E.164 keeps phone numbers unambiguous by requiring a country code.
            ContactType::Phone => preg_match('/^\+[1-9][0-9]{7,14}$/', $this->value) === 1,
        };

        if (! $valid) {
            throw new InvalidArgumentException(match ($type) {
                ContactType::Email => 'The contact email address is not valid.',
                ContactType::Phone => 'The phone number must use international format, for example +234*********.',
            });
        }
    }

    public function type(): ContactType
    {
        return $this->type;
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{type: string, value: string} */
    protected function toArray(): array
    {
        return ['type' => $this->type->value, 'value' => $this->value];
    }
}
