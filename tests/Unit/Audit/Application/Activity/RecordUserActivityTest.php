<?php

declare(strict_types=1);

use Audit\Application\Activity\RecordUserActivity;
use Audit\Domain\Activity\ActivityLogEntry;
use Audit\Domain\Activity\Repository\ActivityLogRepository;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

it('builds an activity entry without needing Laravel request objects', function (): void {
    $id = Uuid::generate();
    $occurredOn = new DateTimeImmutable('2026-09-26T08:30:00+01:00');
    $repository = Mockery::mock(ActivityLogRepository::class);
    $repository->shouldReceive('append')->once()->with(Mockery::on(
        fn (ActivityLogEntry $activity): bool => $activity->id()->equals($id)
            && $activity->actorType() === 'App\\Models\\User'
            && $activity->actorId() === '42'
            && $activity->action() === 'accounts.freeze'
            && $activity->httpMethod() === 'PATCH'
            && $activity->responseStatus() === 200
            && $activity->ipAddress() === '203.0.113.10'
            && $activity->userAgent() === 'Banking App'
            && $activity->metadata() === ['route' => 'accounts/{account}/freeze']
            && $activity->occurredOn() === $occurredOn
    ));
    $uuidGenerator = Mockery::mock(UuidGenerator::class);
    $uuidGenerator->shouldReceive('generate')->once()->andReturn($id);
    $clock = Mockery::mock(Clock::class);
    $clock->shouldReceive('now')->once()->andReturn($occurredOn);

    (new RecordUserActivity($repository, $uuidGenerator, $clock))
        ->record(
            'App\\Models\\User',
            '42',
            'accounts.freeze',
            'PATCH',
            200,
            '203.0.113.10',
            'Banking App',
            ['route' => 'accounts/{account}/freeze'],
        );
});
