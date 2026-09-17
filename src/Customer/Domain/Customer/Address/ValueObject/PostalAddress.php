<?php

declare(strict_types=1);

namespace Customer\Domain\Customer\Address\ValueObject;

use InvalidArgumentException;
use Shared\Domain\ValueObject\ValueObject;

/** Holds a deliverable address in a consistent, whitespace-cleaned form. */
final class PostalAddress extends ValueObject
{
    public function __construct(
        private string $lineOne,
        private ?string $lineTwo,
        private string $city,
        private string $stateOrRegion,
        private string $postalCode,
        private CountryCode $countryCode,
    ) {
        $this->lineOne = self::normalize($lineOne);
        $this->lineTwo = self::optional($lineTwo);
        $this->city = self::normalize($city);
        $this->stateOrRegion = self::normalize($stateOrRegion);
        $this->postalCode = strtoupper(self::normalize($postalCode));

        if ($this->lineOne === '' || $this->city === '' || $this->stateOrRegion === '' || $this->postalCode === '') {
            throw new InvalidArgumentException('Address line one, city, state or region, and postal code are required.');
        }

        foreach ([$this->lineOne, $this->lineTwo, $this->city, $this->stateOrRegion] as $part) {
            if ($part !== null && mb_strlen($part) > 150) {
                throw new InvalidArgumentException('Each address line or place name must be 150 characters or fewer.');
            }
        }

        if (mb_strlen($this->postalCode) > 20) {
            throw new InvalidArgumentException('The postal code must be 20 characters or fewer.');
        }
    }

    public function lineOne(): string
    {
        return $this->lineOne;
    }

    public function lineTwo(): ?string
    {
        return $this->lineTwo;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function stateOrRegion(): string
    {
        return $this->stateOrRegion;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }

    /** @return array<string, string|null> */
    protected function toArray(): array
    {
        return [
            'line_one' => $this->lineOne,
            'line_two' => $this->lineTwo,
            'city' => $this->city,
            'state_or_region' => $this->stateOrRegion,
            'postal_code' => $this->postalCode,
            'country_code' => $this->countryCode->value(),
        ];
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private static function optional(?string $value): ?string
    {
        $normalized = $value === null ? '' : self::normalize($value);

        return $normalized === '' ? null : $normalized;
    }
}
