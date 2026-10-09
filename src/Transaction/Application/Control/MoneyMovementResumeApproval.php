<?php

declare(strict_types=1);

namespace Transaction\Application\Control;

use Shared\Domain\Identifier\Uuid;

interface MoneyMovementResumeApproval
{
    public function request(string $reason, Uuid $requestedBy): MoneyMovementResumeRequest;

    public function approve(Uuid $requestId, Uuid $approvedBy): MoneyMovementResumeRequest;

    public function reject(Uuid $requestId, Uuid $rejectedBy, string $reason): MoneyMovementResumeRequest;

    public function cancel(Uuid $requestId, Uuid $cancelledBy, string $reason): MoneyMovementResumeRequest;
}
