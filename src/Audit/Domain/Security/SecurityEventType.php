<?php

declare(strict_types=1);

namespace Audit\Domain\Security;

/** The security outcomes that operators may need to investigate. */
enum SecurityEventType: string
{
    case LoginSucceeded = 'identity.login_succeeded';
    case LoginFailed = 'identity.login_failed';
    case UnverifiedLoginBlocked = 'identity.unverified_login_blocked';
    case AccessDenied = 'identity.access_denied';
}
