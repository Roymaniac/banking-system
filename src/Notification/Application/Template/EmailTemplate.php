<?php

declare(strict_types=1);

namespace Notification\Application\Template;

/** The approved email templates and the data each one requires. */
enum EmailTemplate: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
    case SecurityAlert = 'security_alert';
    case MoneyMovementResumeApproval = 'money_movement_resume_approval';
    case MoneyMovementResumeReminder = 'money_movement_resume_reminder';
    case MoneyMovementResumeOutcome = 'money_movement_resume_outcome';

    public function subject(): string
    {
        return match ($this) {
            self::EmailVerification => 'Verify your email address',
            self::PasswordReset => 'Reset your password',
            self::SecurityAlert => 'Security alert for your account',
            self::MoneyMovementResumeApproval => 'Money movement resume approval required',
            self::MoneyMovementResumeReminder => 'Urgent: money movement resume approval expires soon',
            self::MoneyMovementResumeOutcome => 'Money movement resume request updated',
        };
    }

    public function view(): string
    {
        return 'emails.'.$this->value;
    }

    /** @return list<string> */
    public function requiredVariables(): array
    {
        return match ($this) {
            self::EmailVerification => ['verificationUrl', 'expiresAt'],
            self::PasswordReset => ['resetUrl', 'expiresAt'],
            self::SecurityAlert => ['action', 'occurredAt'],
            self::MoneyMovementResumeApproval => ['requestId', 'expiresAt', 'reviewUrl'],
            self::MoneyMovementResumeReminder => ['requestId', 'expiresAt', 'reviewUrl'],
            self::MoneyMovementResumeOutcome => ['requestId', 'status', 'reviewUrl'],
        };
    }
}
