<?php

declare(strict_types=1);

use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Identifier\Identifier;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Repository\AggregateRepository;

/**
 * In memory aggregate repository
 */
final class InMemoryAggregateRepository implements AggregateRepository
{
    private array $aggregates = [];

    public function save(AggregateRoot $aggregate): void
    {
        $this->aggregates[$aggregate->id()->value()] = $aggregate;
    }

    public function find(Identifier $id): ?AggregateRoot
    {
        return $this->aggregates[$id->value()] ?? null;
    }

    public function exists(Identifier $id): bool
    {
        return array_key_exists($id->value(), $this->aggregates);
    }
}

final class RepositoryTestAggregate extends AggregateRoot
{
    public function __construct(
        private readonly Uuid $id,
    ) {}

    public function id(): Identifier
    {
        return $this->id;
    }
}

it('saves and finds an aggregate by its identifier', function (): void {
    $repository = new InMemoryAggregateRepository;
    $aggregate = new RepositoryTestAggregate(Uuid::generate());

    $repository->save($aggregate);

    expect($repository->find($aggregate->id()))->toBe($aggregate);
});

it('returns null when an aggregate does not exist', function (): void {
    $repository = new InMemoryAggregateRepository;

    expect($repository->find(Uuid::generate()))->toBeNull();
});

it('checks whether an aggregate exists', function (): void {
    $repository = new InMemoryAggregateRepository;
    $aggregate = new RepositoryTestAggregate(Uuid::generate());

    expect($repository->exists($aggregate->id()))->toBeFalse();

    $repository->save($aggregate);

    expect($repository->exists($aggregate->id()))->toBeTrue();
});
