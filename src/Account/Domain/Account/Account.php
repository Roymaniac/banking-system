<?php

declare(strict_types=1);

namespace Account\Domain\Account;

use Account\Domain\Account\Event\AccountActivated;
use Account\Domain\Account\Event\AccountCreated;
use Account\Domain\Account\Event\AccountNumberAssigned;
use Account\Domain\Account\Exception\AccountNumberAlreadyAssigned;
use Account\Domain\Account\Exception\AccountNumberRequired;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

/**
 * The Account aggregate represents one banking relationship with a customer.
 */
final class Account extends AggregateRoot
{
    private function __construct(
        private readonly AccountId $id,
        private readonly CustomerId $customerId,
        private readonly AccountType $type,
        private readonly CurrencyCode $currency,
        private readonly DateTimeImmutable $createdAt,
        private ?AccountNumber $number = null,
        private AccountStatus $status = AccountStatus::Pending,
    ) {}

    public static function create(
        AccountId $id,
        CustomerId $customerId,
        AccountType $type,
        CurrencyCode $currency,
        DateTimeImmutable $createdAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): self {
        $account = new self($id, $customerId, $type, $currency, $createdAt);
        $account->record(new AccountCreated(
            $eventId,
            $id,
            $createdAt,
            $customerId,
            $type,
            $currency,
            $correlationId,
        ));

        return $account;
    }

    /** Rebuilds a stored account without recording another creation event. */
    public static function reconstitute(
        AccountId $id,
        CustomerId $customerId,
        AccountType $type,
        CurrencyCode $currency,
        DateTimeImmutable $createdAt,
        int $version,
        ?AccountNumber $number = null,
        AccountStatus $status = AccountStatus::Pending,
    ): self {
        $account = new self($id, $customerId, $type, $currency, $createdAt, $number, $status);
        $account->reconstituteAtVersion($version);

        return $account;
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function type(): AccountType
    {
        return $this->type;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function number(): ?AccountNumber
    {
        return $this->number;
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /** Makes a fully provisioned pending account available for banking. */
    public function activate(
        DateTimeImmutable $activatedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        if ($this->number === null) {
            throw AccountNumberRequired::create();
        }

        if ($this->status !== AccountStatus::Pending) {
            throw InvalidAccountStatusTransition::fromTo($this->status, AccountStatus::Active);
        }

        $this->status = AccountStatus::Active;
        $this->record(new AccountActivated(
            $eventId,
            $this->id,
            $this->version() + 1,
            $activatedAt,
            $correlationId,
        ));
    }

    /** Assigns the public account number once; it cannot later be replaced. */
    public function assignNumber(
        AccountNumber $number,
        DateTimeImmutable $assignedAt,
        Uuid $eventId,
        ?CorrelationId $correlationId = null,
    ): void {
        if ($this->number !== null) {
            throw AccountNumberAlreadyAssigned::create();
        }

        $this->number = $number;
        $this->record(new AccountNumberAssigned(
            $eventId,
            $this->id,
            $this->version() + 1,
            $assignedAt,
            $number,
            $correlationId,
        ));
    }
}
