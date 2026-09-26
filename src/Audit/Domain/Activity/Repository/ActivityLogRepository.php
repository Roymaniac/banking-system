<?php

declare(strict_types=1);

namespace Audit\Domain\Activity\Repository;

use Audit\Domain\Activity\ActivityLogEntry;

interface ActivityLogRepository
{
    /** Adds a new activity without rewriting earlier user history. */
    public function append(ActivityLogEntry $activity): void;
}
