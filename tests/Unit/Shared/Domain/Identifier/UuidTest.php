<?php

declare(strict_types=1);

use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Shared\Infrastructure\Identifier\NativeUuidGenerator;

it('normalizes UUID values and compares them by value', function (): void {
    $uuid = new Uuid('A0Eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');

    expect($uuid->value())->toBe('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11')
        ->and((string) $uuid)->toBe('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
});

it('rejects invalid UUID values', function (): void {
    new Uuid('not-a-uuid');
})->throws(InvalidArgumentException::class);

it('generates version four UUIDs without a framework dependency', function (): void {
    $uuid = (new NativeUuidGenerator)->generate();

    expect($uuid)->toBeInstanceOf(Uuid::class)
        ->and($uuid->value())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('can generate a typed correlation identifier', function (): void {
    expect(CorrelationId::generate())->toBeInstanceOf(CorrelationId::class);
});
