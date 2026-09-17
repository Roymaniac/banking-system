<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/**
 * Represents a calendar birth date, without attaching a time of day to it.
 */
final class DateOfBirth extends ValueObject
{
    private function __construct(private readonly DateTimeImmutable $value) {}

    public static function fromString(string $value, DateTimeImmutable $today): self
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('The date of birth must be a real date in YYYY-MM-DD format.');
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('The date of birth must be a real date in YYYY-MM-DD format.');
        }

        $todayAsDate = new DateTimeImmutable($today->format('Y-m-d'));

        if ($date > $todayAsDate) {
            throw new InvalidArgumentException('The date of birth cannot be in the future.');
        }

        return new self($date);
    }

    public function value(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function ageAt(DateTimeImmutable $date): int
    {
        $dateOnly = new DateTimeImmutable($date->format('Y-m-d'));

        if ($dateOnly < $this->value) {
            throw new InvalidArgumentException('Age cannot be calculated before the date of birth.');
        }

        return $this->value->diff($dateOnly)->y;
    }

    /** @return array{value: string} */
    protected function toArray(): array
    {
        return ['value' => $this->value()];
    }
}
