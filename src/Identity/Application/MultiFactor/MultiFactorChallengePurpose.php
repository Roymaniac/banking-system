<?php

declare(strict_types=1);

namespace Identity\Application\MultiFactor;

/** Identifies what may be issued after a second factor succeeds. */
enum MultiFactorChallengePurpose: string
{
    case Login = 'login';
    case PrivilegedAccess = 'privileged_access';
    case HighValueTransfer = 'high_value_transfer';
}
