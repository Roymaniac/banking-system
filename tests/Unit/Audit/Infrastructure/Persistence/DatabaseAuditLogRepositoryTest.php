<?php

declare(strict_types=1);

use Audit\Domain\Log\Repository\AuditLogRepository;
use Audit\Infrastructure\Persistence\DatabaseAuditLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Shared\Contracts\EventPublisher;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Identifier\CorrelationId;
use Shared\Domain\Identifier\Uuid;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

final readonly class DatabaseAuditLogTestEvent extends DomainEvent
{
    public static function eventName(): string
    {
        return 'account.tested';
    }

    public function payload(): array
    {
        return ['safe_detail' => 'recorded'];
    }
}

it('binds the audit log contract to the database repository', function (): void {
    expect(app(AuditLogRepository::class))->toBeInstanceOf(DatabaseAuditLogRepository::class);
});

it('records published domain events with their metadata and payload', function (): void {
    $eventId = Uuid::generate();
    $aggregateId = Uuid::generate();
    $correlationId = CorrelationId::generate();
    $event = new DatabaseAuditLogTestEvent(
        $eventId,
        $aggregateId,
        4,
        new DateTimeImmutable('2026-09-25T10:00:00+01:00'),
        $correlationId,
    );

    app(EventPublisher::class)->publish([$event]);

    $record = DB::table('audit_log')->where('event_id', $eventId->value())->first();

    expect($record)->not->toBeNull()
        ->and($record->event_name)->toBe('account.tested')
        ->and($record->aggregate_type)->toBe(Uuid::class)
        ->and($record->aggregate_id)->toBe($aggregateId->value())
        ->and($record->aggregate_version)->toBe(4)
        ->and($record->correlation_id)->toBe($correlationId->value())
        ->and(json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR))
        ->toBe(['safe_detail' => 'recorded']);
});

it('does not duplicate an audit entry when the same event is published again', function (): void {
    $event = new DatabaseAuditLogTestEvent(
        Uuid::generate(),
        Uuid::generate(),
        1,
        new DateTimeImmutable,
    );

    app(EventPublisher::class)->publish([$event]);
    app(EventPublisher::class)->publish([$event]);

    expect(DB::table('audit_log')->count())->toBe(1);
});
