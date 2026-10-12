<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Domain\Risk\TransferRiskResolution;

interface TransferRiskCaseReview
{
    public function claim(Uuid $caseId, UserId $reviewerId): TransferRiskCase;

    public function resolve(
        Uuid $caseId,
        UserId $reviewerId,
        TransferRiskResolution $resolution,
        string $note,
    ): TransferRiskCase;
}
