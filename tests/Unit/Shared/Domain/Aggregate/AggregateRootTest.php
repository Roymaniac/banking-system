<?php

declare(strict_types=1);

use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\ValueObject;

final class AggregateDummyId extends ValueObject
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

final class DummyEvent implements DomainEvent
{
    public function __construct(
        private string $eventId,
        private DateTimeImmutable $occurredOn,
    ) {}

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventName(): string
    {
        return 'dummy.event';
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

final class DummyAggregate extends AggregateRoot
{
    public function __construct(
        private readonly AggregateDummyId $id,
    ) {}

    public function id(): ValueObject
    {
        return $this->id;
    }

    public function doSomething(): void
    {
        $this->record(
            new DummyEvent(
                uniqid('', true),
                new DateTimeImmutable()
            )
        );
    }
}

it('records domain events', function (): void {
    $aggregate = new DummyAggregate(
        new AggregateDummyId('aggregate-001')
    );

    $aggregate->doSomething();

    expect($aggregate->recordedEvents())->toHaveCount(1);
});

it('releases recorded events', function (): void {
    $aggregate = new DummyAggregate(
        new AggregateDummyId('aggregate-001')
    );

    $aggregate->doSomething();

    $events = $aggregate->releaseEvents();

    expect($events)->toHaveCount(1);
    expect($aggregate->recordedEvents())->toBeEmpty();
});
