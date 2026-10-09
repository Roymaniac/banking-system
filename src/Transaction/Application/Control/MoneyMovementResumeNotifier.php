<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

/** Alerts eligible reviewers without making the transaction context depend on email. */
interface MoneyMovementResumeNotifier
{
    public function pending(MoneyMovementResumeRequest $request): void;
}
