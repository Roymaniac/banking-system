<?php

declare(strict_types=1);

namespace Reporting\Application\Customer\View;

use DateTimeImmutable;

/** The complete read-only customer overview returned by the reporting module. */
final readonly class CustomerReportView
{
    /**
     * @param  list<CustomerAddressView>  $addresses
     * @param  list<CustomerContactView>  $contacts
     * @param  list<CustomerAccountView>  $accounts
     */
    public function __construct(
        public string $customerId,
        public string $userId,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public DateTimeImmutable $dateOfBirth,
        public DateTimeImmutable $registeredAt,
        public array $addresses,
        public array $contacts,
        public array $accounts,
    ) {}

    /** Makes the customer's complete display name without leaving extra spaces. */
    public function fullName(): string
    {
        return implode(' ', array_filter([
            $this->firstName,
            $this->middleName,
            $this->lastName,
        ], fn (?string $part): bool => $part !== null && $part !== ''));
    }

    public function accountCount(): int
    {
        return count($this->accounts);
    }
}
