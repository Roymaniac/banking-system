<?php

declare(strict_types=1);

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class EventTestEvent extends DomainEvent
{
    public static function eventName(): string
    {
        return 'shared.test-event';
    }
}

it('retains the metadata required to trace an event', function (): void {
    $eventId = Uuid::generate();
    $aggregateId = Uuid::generate();
    $correlationId = CorrelationId::generate();
    $occurredOn = new DateTimeImmutable('2026-08-07T12:00:00+00:00');

    $event = new EventTestEvent($eventId, $aggregateId, 3, $occurredOn, $correlationId);

    expect($event->eventId())->toBe($eventId)
        ->and($event->aggregateId())->toBe($aggregateId)
        ->and($event->aggregateVersion())->toBe(3)
        ->and($event->occurredOn())->toBe($occurredOn)
        ->and($event->correlationId())->toBe($correlationId)
        ->and(EventTestEvent::eventName())->toBe('shared.test-event');
});

it('does not allow an event at a non-positive aggregate version', function (): void {
    new EventTestEvent(Uuid::generate(), Uuid::generate(), 0, new DateTimeImmutable);
})->throws(InvalidArgumentException::class);
