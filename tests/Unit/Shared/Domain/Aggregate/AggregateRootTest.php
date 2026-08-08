<?php

declare(strict_types=1);

use Shared\Domain\Aggregate\AggregateRoot;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Identifier;
use Shared\Domain\Identifier\Uuid;

final class AggregateDummyId extends Identifier
{
    public function __construct(
        private string $value,
    ) {}

    public function value(): string
    {
        return $this->value;
    }
}

final readonly class DummyEvent extends DomainEvent
{
    public function __construct(
        Uuid $eventId,
        Identifier $aggregateId,
        int $aggregateVersion,
    ) {
        parent::__construct($eventId, $aggregateId, $aggregateVersion, new DateTimeImmutable);
    }

    public static function eventName(): string
    {
        return 'dummy.event';
    }
}

final class DummyAggregate extends AggregateRoot
{
    public function __construct(
        private readonly AggregateDummyId $id,
    ) {}

    public function id(): Identifier
    {
        return $this->id;
    }

    public function doSomething(): void
    {
        $this->record(
            new DummyEvent(
                Uuid::generate(),
                $this->id(),
                $this->version() + 1,
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

it('pulls recorded events and clears the aggregate queue', function (): void {
    $aggregate = new DummyAggregate(
        new AggregateDummyId('aggregate-001')
    );

    $aggregate->doSomething();

    $events = $aggregate->pullDomainEvents();

    expect($events)->toHaveCount(1);
    expect($aggregate->recordedEvents())->toBeEmpty();
});

it('increments the aggregate version for each recorded event', function (): void {
    $aggregate = new DummyAggregate(new AggregateDummyId('aggregate-001'));

    $aggregate->doSomething();

    expect($aggregate->version())->toBe(1);
});
