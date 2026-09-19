<?php

declare(strict_types=1);

use Account\Application\Freeze\FreezeAccount;
use Account\Application\Freeze\FreezeAccountCommand;
use Account\Application\Freeze\UnfreezeAccount;
use Account\Application\Freeze\UnfreezeAccountCommand;
use Account\Domain\Account\Account;
use Account\Domain\Account\Event\AccountFrozen;
use Account\Domain\Account\Event\AccountUnfrozen;
use Account\Domain\Account\Repository\AccountRepository;
use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\AccountType;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Account\Domain\Account\ValueObject\FreezeReason;
use Customer\Domain\Customer\ValueObject\CustomerId;
use Shared\Contracts\Clock;
use Shared\Contracts\EventPublisher;
use Shared\Contracts\TransactionManager;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

final readonly class AccountFreezeTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-18T12:00:00+01:00');
    }
}

final class AccountFreezeTestTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}

final class AccountFreezeTestPublisher implements EventPublisher
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(array $events): void
    {
        $this->published = $events;
    }
}

function accountFreezeUseCaseAccount(): Account
{
    return Account::reconstitute(
        AccountId::generate(),
        CustomerId::generate(),
        AccountType::Savings,
        new CurrencyCode('NGN'),
        new DateTimeImmutable('2026-09-18T09:00:00+01:00'),
        3,
        new AccountNumber('1234567890'),
        AccountStatus::Active,
    );
}

it('freezes and unfreezes an account through the application services', function (): void {
    $account = accountFreezeUseCaseAccount();
    $accounts = Mockery::mock(AccountRepository::class);
    $accounts->shouldReceive('findById')->twice()->with($account->id())->andReturn($account);
    $accounts->shouldReceive('save')->twice()->with($account);
    $ids = Mockery::mock(UuidGenerator::class);
    $ids->shouldReceive('generate')->twice()->andReturn(Uuid::generate(), Uuid::generate());
    $publisher = new AccountFreezeTestPublisher;
    $clock = new AccountFreezeTestClock;
    $transactions = new AccountFreezeTestTransactions;

    (new FreezeAccount($accounts, $clock, $ids, $transactions, $publisher))
        ->handle(new FreezeAccountCommand($account->id(), FreezeReason::CourtOrder));

    expect($account->status())->toBe(AccountStatus::Frozen)
        ->and($publisher->published[0])->toBeInstanceOf(AccountFrozen::class);

    (new UnfreezeAccount($accounts, $clock, $ids, $transactions, $publisher))
        ->handle(new UnfreezeAccountCommand($account->id()));

    expect($account->status())->toBe(AccountStatus::Active)
        ->and($publisher->published)->toHaveCount(1)
        ->and($publisher->published[0])->toBeInstanceOf(AccountUnfrozen::class);
});
