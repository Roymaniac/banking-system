<?php

declare(strict_types=1);

namespace Identity\Application\Security;

/** Names the account-security action that a customer should know about. */
enum SecurityAlert: string
{
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case MultiFactorEnabled = 'multi_factor_enabled';
    case MultiFactorDisabled = 'multi_factor_disabled';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case HighValueTransferApproved = 'high_value_transfer_approved';
}
