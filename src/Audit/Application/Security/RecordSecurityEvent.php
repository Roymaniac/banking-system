<?php

declare(strict_types=1);

namespace Audit\Application\Security;

use Audit\Domain\Security\Repository\SecurityEventRepository;
use Audit\Domain\Security\SecurityEvent;
use Audit\Domain\Security\SecurityEventType;
use Audit\Domain\Security\SecuritySeverity;
use Shared\Contracts\Clock;
use Shared\Domain\Identifier\UuidGenerator;

/** Creates a timestamped security event and sends it to durable storage. */
final readonly class RecordSecurityEvent
{
    public function __construct(
        private SecurityEventRepository $events,
        private UuidGenerator $uuidGenerator,
        private Clock $clock,
    ) {}

    /** @param array<string, bool|int|float|string|null> $details */
    public function record(
        SecurityEventType $type,
        SecuritySeverity $severity,
        ?string $subjectId,
        ?string $subjectFingerprint,
        ?string $ipAddress,
        ?string $userAgent,
        array $details = [],
    ): void {
        $this->events->append(
            new SecurityEvent(
                $this->uuidGenerator->generate(),
                $type,
                $severity,
                $subjectId,
                $subjectFingerprint,
                $ipAddress,
                $userAgent,
                $details,
                $this->clock->now(),
            )
        );
    }
}
