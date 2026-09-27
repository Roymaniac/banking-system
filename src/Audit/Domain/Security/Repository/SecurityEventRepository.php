<?php

declare(strict_types=1);

namespace Audit\Domain\Security\Repository;

use Audit\Domain\Security\SecurityEvent;

interface SecurityEventRepository
{
    /** Appends a security event without changing existing investigation history. */
    public function append(SecurityEvent $event): void;
}
