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
    case PrivilegedAccessGranted = 'identity.privileged_access_granted';
    case PrivilegedAccessDenied = 'identity.privileged_access_denied';
    case SessionRevoked = 'identity.session_revoked';
    case AllSessionsRevoked = 'identity.all_sessions_revoked';
    case MultiFactorChallengeFailed = 'identity.mfa_challenge_failed';
    case MoneyMovementBreakGlassResumed = 'operations.money_movement_break_glass_resumed';
}
