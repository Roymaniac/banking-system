<?php

declare(strict_types=1);

use Shared\Domain\Exception\AggregateNotFound;
use Shared\Domain\Exception\ConcurrencyException;
use Shared\Domain\Exception\DomainException;
use Shared\Domain\Identifier\Uuid;

it('marks domain failures as logic exceptions', function (): void {
    expect(new DomainException('A business rule was violated.'))->toBeInstanceOf(LogicException::class);
});

it('describes an aggregate that could not be found', function (): void {
    $id = new Uuid('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');

    expect(AggregateNotFound::withId($id))
        ->toBeInstanceOf(DomainException::class)
        ->getMessage()->toBe('No aggregate was found with identifier "a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11".');
});

it('describes a conflicting aggregate version', function (): void {
    $id = new Uuid('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');

    expect(ConcurrencyException::forAggregate($id, 2, 3))
        ->toBeInstanceOf(DomainException::class)
        ->getMessage()->toBe('Aggregate "a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11" was expected at version 2 but is at version 3.');
});
