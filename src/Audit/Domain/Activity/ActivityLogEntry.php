<?php

declare(strict_types=1);

namespace Audit\Domain\Activity;

use DateTimeImmutable;
use Shared\Domain\Identifier\Uuid;

/** Describes one authenticated action performed through the application. */
final readonly class ActivityLogEntry
{
    /**
     * @param  array<string, bool|int|float|string|null>  $metadata
     */
    public function __construct(
        private Uuid $id,
        private string $actorType,
        private string $actorId,
        private string $action,
        private string $httpMethod,
        private int $responseStatus,
        private ?string $ipAddress,
        private ?string $userAgent,
        private array $metadata,
        private DateTimeImmutable $occurredOn,
    ) {}

    public function id(): Uuid
    {
        return $this->id;
    }

    public function actorType(): string
    {
        return $this->actorType;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function httpMethod(): string
    {
        return $this->httpMethod;
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
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
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
