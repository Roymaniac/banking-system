<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

interface TransferRiskCaseNotifier
{
    public function opened(TransferRiskCase $case): int;

    public function reminder(TransferRiskCase $case): int;

    public function escalated(TransferRiskCase $case): int;
}
