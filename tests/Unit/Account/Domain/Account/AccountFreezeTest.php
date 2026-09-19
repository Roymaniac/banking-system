<?php

declare(strict_types=1);

use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountFrozen;
use Account\Domain\Account\Event\AccountUnfrozen;
use Account\Domain\Account\Exception\InvalidAccountStatusTransition;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Account\Domain\Account\ValueObject\FreezeReason;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Domain\Identifier\Uuid;

function accountFreezeTestAccount(AccountStatus $status = AccountStatus::Active): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        $status,
    );
}

it('freezes an active account with a controlled reason and timestamp', function (): void {
    $account = accountFreezeTestAccount();
    $frozenAt = new DateTimeImmutable('2026-09-18T12:00:00+01:00');
    $account->freeze(FreezeReason::SuspectedFraud, $frozenAt, Uuid::generate());

    $event = $account->recordedEvents()[0];

    expect($account->status())->toBe(AccountStatus::Frozen)
        ->and($account->isActive())->toBeFalse()
        ->and($account->freezeReason())->toBe(FreezeReason::SuspectedFraud)
        ->and($account->frozenAt())->toEqual($frozenAt)
        ->and($event)->toBeInstanceOf(AccountFrozen::class)
        ->and($event->payload())->toBe(['reason' => 'suspected_fraud']);
});

it('unfreezes an account and clears its current restriction details', function (): void {
    $frozenAt = new DateTimeImmutable('2026-09-18T12:00:00+01:00');
    $account = Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        4,
        new AccountNumber('1234567890'),
        AccountStatus::Frozen,
        FreezeReason::ComplianceReview,
        $frozenAt,
    );

    $account->unfreeze(new DateTimeImmutable('2026-09-18T13:00:00+01:00'), Uuid::generate());

    expect($account->status())->toBe(AccountStatus::Active)
        ->and($account->freezeReason())->toBeNull()
        ->and($account->frozenAt())->toBeNull()
        ->and($account->recordedEvents()[0])->toBeInstanceOf(AccountUnfrozen::class);
});

it('does not freeze a pending account', function (): void {
    accountFreezeTestAccount(AccountStatus::Pending)
        ->freeze(FreezeReason::CustomerRequest, new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from pending status to frozen status.');

it('does not unfreeze an active account', function (): void {
    accountFreezeTestAccount()->unfreeze(new DateTimeImmutable, Uuid::generate());
})->throws(InvalidAccountStatusTransition::class, 'An account cannot move from active status to active status.');
