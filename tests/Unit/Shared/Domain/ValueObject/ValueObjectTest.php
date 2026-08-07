<?php

declare(strict_types=1);

use Shared\Domain\ValueObject\ValueObject;

final class ValueObjectTest extends ValueObject
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

    public function value(): string
    {
        return $this->value;
    }
}

it('compare two value objects with the same values as equal', function (): void {
    $first = new ValueObjectTest('BANKING');
    $second = new ValueObjectTest('BANKING');

    expect($first->equals($second))->toBeTrue();
});

it('compare two value objects with different values as not equal', function (): void {
    $first = new ValueObjectTest('CORE');
    $second = new ValueObjectTest('BANKING');

    expect($first->equals($second))->toBeFalse();
});

it('serialize a value object into an array', function (): void {
    $valueObject = new ValueObjectTest('BANKING');

    expect($valueObject->jsonSerialize())->toBe([
        'value' => 'BANKING',
    ]);
});
