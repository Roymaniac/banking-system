<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\Uuid;
use Shared\Infrastructure\Event\LaravelEventPublisher;

final readonly class EventPublisherTestEvent extends DomainEvent
{
    public static function eventName(): string
    {
        return 'shared.event-publisher-test';
    }
}

it('dispatches every domain event through Laravel', function (): void {
    $firstEvent = new EventPublisherTestEvent(
        Uuid::generate(),
        Uuid::generate(),
        1,
        new DateTimeImmutable,
    );
    $secondEvent = new EventPublisherTestEvent(
        Uuid::generate(),
        Uuid::generate(),
        1,
        new DateTimeImmutable,
    );

    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->with($firstEvent);
    $dispatcher->shouldReceive('dispatch')->once()->with($secondEvent);

    (new LaravelEventPublisher($dispatcher))->publish([$firstEvent, $secondEvent]);
});
