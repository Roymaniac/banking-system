<?php

declare(strict_types=1);

namespace Transaction\Application\Risk;

interface TransferRiskCaseSla
{
    /** @return array{reminded: int, escalated: int} */
    public function processDue(): array;
}
