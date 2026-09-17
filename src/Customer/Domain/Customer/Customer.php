<?php

declare(strict_types=1);

namespace Customer\Domain\Customer;

use Customer\Domain\Customer\Event\CustomerProfileCreated;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Customer\Domain\Customer\ValueObject\DateOfBirth;
use Customer\Domain\Customer\ValueObject\PersonalName;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * The Customer aggregate owns personal profile information used by banking.
 */
final class Customer extends AggregateRoot
{
    private function __construct(
        private readonly CustomerId $id,
        private readonly UserId $userId,
        private PersonalName $name,
        private DateOfBirth $dateOfBirth,
        private readonly DateTimeImmutable $registeredAt,
    ) {}

    public static function create(
        CustomerId $id,
        UserId $userId,
        PersonalName $name,
        DateOfBirth $dateOfBirth,
        DateTimeImmutable $registeredAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $customer = new self($id, $userId, $name, $dateOfBirth, $registeredAt);
        $customer->record(new CustomerProfileCreated(
            $eventId,
            $id,
            $registeredAt,
            $userId,
            $correlationId,
        ));

        return $customer;
    }

    /** Rebuilds a stored profile without creating a second creation event. */
    public static function reconstitute(
        CustomerId $id,
        UserId $userId,
        PersonalName $name,
        DateOfBirth $dateOfBirth,
        DateTimeImmutable $registeredAt,
        int $version,
    ): self {
        $customer = new self($id, $userId, $name, $dateOfBirth, $registeredAt);
        $customer->reconstituteAtVersion($version);

        return $customer;
    }

    public function id(): CustomerId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function name(): PersonalName
    {
        return $this->name;
    }

    public function dateOfBirth(): DateOfBirth
    {
        return $this->dateOfBirth;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
