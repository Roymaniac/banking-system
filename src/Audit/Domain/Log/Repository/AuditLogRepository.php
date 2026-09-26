<?php

declare(strict_types=1);

namespace Audit\Domain\Log\Repository;

use Audit\Domain\Log\AuditLogEntry;

interface AuditLogRepository
{
    /** Adds an entry without changing or deleting any earlier audit history. */
    public function append(AuditLogEntry $entry): void;
}
