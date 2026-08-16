<?php

declare(strict_types=1);

use Shared\Contracts\Clock;
use Shared\Infrastructure\Clock\SystemClock;
use Tests\TestCase;

uses(TestCase::class);

it('provides the current time as an immutable date and time', function (): void {
    $clock = new SystemClock;
    $before = new DateTimeImmutable;

    $now = $clock->now();

    $after = new DateTimeImmutable;

    expect($clock)->toBeInstanceOf(Clock::class)
        ->and($now)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($now)->toBeGreaterThanOrEqual($before)
        ->and($now)->toBeLessThanOrEqual($after);
});

it('registers one shared clock instance for the application', function (): void {
    expect(app(Clock::class))->toBeInstanceOf(SystemClock::class)
        ->and(app(Clock::class))->toBe(app(Clock::class));
});
