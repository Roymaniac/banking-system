<?php

declare(strict_types=1);

namespace Identity\Application\Security;

use DateTimeImmutable;
use Identity\Domain\User\ValueObject\EmailAddress;

/** Sends a durable warning after an important account-security action. */
interface SecurityAlertNotifier
{
    public function send(
        EmailAddress $email,
        SecurityAlert $alert,
        DateTimeImmutable $occurredAt,
    ): void;
}
