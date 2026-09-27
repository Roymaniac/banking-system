<?php

declare(strict_types=1);

namespace Audit\Application\Activity;

use Audit\Domain\Activity\ActivityLogEntry;
use Audit\Domain\Activity\Repository\ActivityLogRepository;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\UuidGenerator;

/** Builds and stores a safe activity record from request information. */
final readonly class RecordUserActivity
{
    public function __construct(
        private ActivityLogRepository $activities,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, bool|int|float|string|null>  $metadata
     */
    public function record(
        string $actorType,
        string $actorId,
        string $action,
        string $httpMethod,
        int $responseStatus,
        ?string $ipAddress,
        ?string $userAgent,
        array $metadata = [],
    ): void {

        $this->activities->append(
            new ActivityLogEntry(
                $this->uuidGenerator->generate(),
                $actorType,
                $actorId,
                $action,
                $httpMethod,
                $responseStatus,
                $ipAddress,
                $userAgent,
                $metadata,
                $this->clock->now(),
            )
        );
    }
}
