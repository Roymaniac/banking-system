<?php

declare(strict_types=1);

namespace Account\Domain\Account;

use Account\Domain\Account\Event\AccountCreated;
use Account\Domain\Account\ValueObject\AccountId;
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
    ): self {
        $account = new self($id, $customerId, $type, $currency, $createdAt);
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
}
