<?php

declare(strict_types=1);

namespace Shared\Application\Health;

/** A safe operational summary containing no credentials or infrastructure addresses. */
final readonly class SystemHealthReport
{
    /** @param array<string, array<string, bool|int|string|null>> $components */
    public function __construct(
        public string $status,
        public array $components,
    ) {}

    public function isReady(): bool
    {
        return $this->status !== 'unhealthy';
    }
}
