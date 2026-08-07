<?php

declare(strict_types=1);

use Shared\Domain\Entity\Entity;
use Shared\Domain\ValueObject\ValueObject;

final class DummyId extends ValueObject
{
    public function __construct(
        private string $value,
    ) {}

    protected function toArray(): array
    {
        return [
            'value' => $this->value,
        ];
    }
}


final class DummyEntity extends Entity
{
    public function __construct(
        private readonly DummyId $id,
    ) {}

    public function id(): ValueObject
    {
        return $this->id;
    }
}

it('compare two entities with the same identifier as equal', function (): void {
    $id = new DummyId('entity-0012');

    $first = new DummyEntity($id);
    $second = new DummyEntity($id);

    expect($first->equals($second))->toBeTrue();
});

it('compare two entities with different identifiers as not equal', function (): void {
    $id1 = new DummyId('entity-0012');
    $id2 = new DummyId('entity-0013');

    $first = new DummyEntity($id1);
    $second = new DummyEntity($id2);

    expect($first->equals($second))->toBeFalse();
});
