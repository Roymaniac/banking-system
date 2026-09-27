<?php

declare(strict_types=1);

use Audit\Application\Log\RecordDomainEvent;
use Audit\Domain\Log\AuditLogEntry;
use Audit\Domain\Log\Repository\AuditLogRepository;
use Shared\Contracts\Clock;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;

final readonly class AuditLogTestEvent extends DomainEvent
{
    public static function eventName(): string
    {
        return 'test.changed';
    }

    public function payload(): array
    {
        return ['field' => 'status'];
    }
}

it('turns a domain event into an audit log entry', function (): void {
    $eventId = Uuid::generate();
    $aggregateId = Uuid::generate();
    $correlationId = CorrelationId::generate();
    $occurredOn = new DateTimeImmutable('2026-09-25T09:00:00+01:00');
    $recordedAt = new DateTimeImmutable('2026-09-25T09:00:01+01:00');
    $event = new AuditLogTestEvent($eventId, $aggregateId, 3, $occurredOn, $correlationId);
    $repository = Mockery::mock(AuditLogRepository::class);
    $repository->shouldReceive('append')->once()->with(Mockery::on(
        fn (AuditLogEntry $entry): bool => $entry->eventId()->equals($eventId)
            && $entry->eventName() === 'test.changed'
            && $entry->aggregateType() === Uuid::class
            && $entry->aggregateId() === $aggregateId->value()
            && $entry->aggregateVersion() === 3
            && $entry->correlationId()?->equals($correlationId) === true
            && $entry->payload() === ['field' => 'status']
            && $entry->occurredOn() === $occurredOn
            && $entry->recordedAt() === $recordedAt
    ));
    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->once()->andReturn($recordedAt);

    (new RecordDomainEvent($repository, $clock))->handle($event);
});
