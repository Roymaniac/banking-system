<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountClosed;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\ClosureReason;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Account\Domain\Account\ValueObject\FreezeReason;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\Uuid;

function accountClosureTestAccount(AccountStatus $status = AccountStatus::Active): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        $status === AccountStatus::Frozen ? 4 : 3,
        new AccountNumber('1234567890'),
        $status,
        $status === AccountStatus::Frozen ? FreezeReason::ComplianceReview : null,
        $status === AccountStatus::Frozen ? new DateTimeImmutable('2026-09-18T12:00:00+01:00') : null,
    );
}

it('permanently closes an active account with an audit reason', function (): void {
    $account = accountClosureTestAccount();
    $closedAt = new DateTimeImmutable('2026-09-19T09:00:00+01:00');
    $account->close(ClosureReason::CustomerRequest, $closedAt, Uuid::generate());

    $event = $account->recordedEvents()[0];

    expect($account->status())->toBe(AccountStatus::Closed)
        ->and($account->isClosed())->toBeTrue()
        ->and($account->isActive())->toBeFalse()
        ->and($account->closureReason())->toBe(ClosureReason::CustomerRequest)
        ->and($account->closedAt())->toEqual($closedAt)
        ->and($event)->toBeInstanceOf(AccountClosed::class)
        ->and($event->payload())->toBe(['reason' => 'customer_request']);
});

it('closes a frozen account and clears its current freeze metadata', function (): void {
    $account = accountClosureTestAccount(AccountStatus::Frozen);
    $account->close(ClosureReason::ComplianceDecision, new DateTimeImmutable, Uuid::generate());

    expect($account->status())->toBe(AccountStatus::Closed)
        ->and($account->freezeReason())->toBeNull()
        ->and($account->frozenAt())->toBeNull()
        ->and($account->closureReason())->toBe(ClosureReason::ComplianceDecision);
});

it('does not close an account that never became active', function (): void {
    accountClosureTestAccount(AccountStatus::Pending)
        ->close(ClosureReason::BankDecision, new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from pending status to closed status.');

it('does not close an already closed account again', function (): void {
    $account = accountClosureTestAccount();
    $account->close(ClosureReason::CustomerRequest, new DateTimeImmutable, Uuid::generate());
    $account->close(ClosureReason::BankDecision, new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from closed status to closed status.');

it('does not reactivate a closed account', function (): void {
    $account = accountClosureTestAccount();
    $account->close(ClosureReason::CustomerRequest, new DateTimeImmutable, Uuid::generate());
    $account->activate(new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from closed status to active status.');
