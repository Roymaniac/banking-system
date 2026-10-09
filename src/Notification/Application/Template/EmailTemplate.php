<?php

declare(strict_types=1);

namespace Notification\Application\Template;

/** The approved email templates and the data each one requires. */
enum EmailTemplate: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
    case MoneyMovementResumeApproval = 'money_movement_resume_approval';

    public function subject(): string
    {
        return match ($this) {
            self::EmailVerification => 'Verify your email address',
            self::PasswordReset => 'Reset your password',
            self::MoneyMovementResumeApproval => 'Money movement resume approval required',
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
            self::MoneyMovementResumeApproval => ['requestId', 'expiresAt', 'reviewUrl'],
        };
    }
}
