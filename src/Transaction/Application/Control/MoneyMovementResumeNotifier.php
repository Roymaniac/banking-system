<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

/** Alerts eligible reviewers without making the transaction context depend on email. */
interface MoneyMovementResumeNotifier
{
    /** Returns how many independent reviewers received a durable notification. */
    public function pending(MoneyMovementResumeRequest $request): int;

    /** Returns how many currently eligible reviewers received the reminder. */
    public function expiringSoon(MoneyMovementResumeRequest $request): int;
}
