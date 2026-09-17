<?php

declare(strict_types=1);

namespace Customer\Application\Profile;

use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\CorrelationId;

/** Carries validated-looking input into the customer-profile use case. */
final readonly class CreateCustomerProfileCommand
{
    public function __construct(
        public UserId $userId,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public string $dateOfBirth,
        public ?CorrelationId $correlationId = null,
    ) {}
}
