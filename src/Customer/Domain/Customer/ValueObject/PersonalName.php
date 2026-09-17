<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/**
 * Stores a person's legal name after cleaning accidental extra spaces.
 */
final class PersonalName extends ValueObject
{
    private string $firstName;

    private ?string $middleName;

    private string $lastName;

    public function __construct(string $firstName, ?string $middleName, string $lastName)
    {
        $this->firstName = self::normalize($firstName);
        $this->lastName = self::normalize($lastName);
        $normalizedMiddleName = $middleName === null ? '' : self::normalize($middleName);
        $this->middleName = $normalizedMiddleName === '' ? null : $normalizedMiddleName;

        if ($this->firstName === '' || $this->lastName === '') {
            throw new InvalidArgumentException('A first name and last name are required.');
        }

        foreach ([$this->firstName, $this->middleName, $this->lastName] as $part) {
            if ($part !== null && mb_strlen($part) > 100) {
                throw new InvalidArgumentException('Each part of a name must be 100 characters or fewer.');
            }
        }
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function middleName(): ?string
    {
        return $this->middleName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    /** @return array{first_name: string, middle_name: ?string, last_name: string} */
    protected function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
        ];
    }

    private static function normalize(string $value): string
    {
        // Names may contain many valid international characters, so we clean
        // whitespace without trying to restrict the alphabet people can use.
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }
}
