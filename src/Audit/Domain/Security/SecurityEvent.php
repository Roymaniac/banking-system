<?php

declare(strict_types=1);

namespace Audit\Domain\Security;

use DateTimeImmutable;
use Shared\Domain\Identifier\Uuid;

/** A safe, permanent record of an authentication or authorization outcome. */
final readonly class SecurityEvent
{
    /** @param array<string, bool|int|float|string|null> $details */
    public function __construct(
        private Uuid $id,
        private SecurityEventType $type,
        private SecuritySeverity $severity,
        private ?string $subjectId,
        private ?string $subjectFingerprint,
        private ?string $ipAddress,
        private ?string $userAgent,
        private array $details,
        private DateTimeImmutable $occurredOn,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function type(): SecurityEventType
    {
        return $this->type;
    }

    public function severity(): SecuritySeverity
    {
        return $this->severity;
    }

    public function subjectId(): ?string
    {
        return $this->subjectId;
    }

    public function subjectFingerprint(): ?string
    {
        return $this->subjectFingerprint;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    /** @return array<string, bool|int|float|string|null> */
    public function details(): array
    {
        return $this->details;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
