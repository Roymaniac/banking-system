<?php

declare(strict_types=1);

use App\Models\User as LaravelUser;
use Identity\Domain\Authorization\ValueObject\Permission;
use Identity\Domain\User\ValueObject\UserId;
use Identity\Infrastructure\Authorization\LaravelGateAuthorizationChecker;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;

it('asks Laravel Gate whether a stored user has the permission', function (): void {
    $userId = UserId::generate();
    $permission = new Permission('accounts.view');
    $storedUser = new LaravelUser;

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('find')->once()->with($userId->value())->andReturn($storedUser);

    $users = Mockery::mock(LaravelUser::class);
    $users->shouldReceive('newQuery')->once()->andReturn($query);

    $userGate = Mockery::mock(Gate::class);
    $userGate->shouldReceive('allows')->once()->with('accounts.view')->andReturnTrue();

    $gate = Mockery::mock(Gate::class);
    $gate->shouldReceive('forUser')->once()->with($storedUser)->andReturn($userGate);

    $checker = new LaravelGateAuthorizationChecker($gate, $users);

    expect($checker->allows($userId, $permission))->toBeTrue();
});

it('denies authorization when the user cannot be found', function (): void {
    $userId = UserId::generate();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('find')->once()->with($userId->value())->andReturnNull();

    $users = Mockery::mock(LaravelUser::class);
    $users->shouldReceive('newQuery')->once()->andReturn($query);

    $gate = Mockery::mock(Gate::class);
    $gate->shouldNotReceive('forUser');

    $checker = new LaravelGateAuthorizationChecker($gate, $users);

    expect($checker->allows($userId, new Permission('accounts.view')))->toBeFalse();
});
