<?php

declare(strict_types=1);

namespace Audit\Domain\Security;

/** Indicates how urgently a recorded security event should be reviewed. */
enum SecuritySeverity: string
{
    case Information = 'information';
    case Warning = 'warning';
}
