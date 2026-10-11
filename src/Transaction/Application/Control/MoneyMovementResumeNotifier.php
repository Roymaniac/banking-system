<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

/** Records durable reviewer alerts and requester outcomes behind one small contract. */
interface MoneyMovementResumeNotifier
{
    /** Returns how many independent reviewers received a durable notification. */
    public function pending(MoneyMovementResumeRequest $request): int;

    /** Returns how many currently eligible reviewers received the reminder. */
    public function expiringSoon(MoneyMovementResumeRequest $request): int;

    /** Returns whether the requester received the final outcome. */
    public function outcome(MoneyMovementResumeRequest $request): int;
}
