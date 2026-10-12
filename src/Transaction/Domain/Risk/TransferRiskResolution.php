<?php

declare(strict_types=1);

namespace Transaction\Domain\Risk;

/** The controlled outcomes available to an authorized risk reviewer. */
enum TransferRiskResolution: string
{
    case Cleared = 'cleared';
    case ConfirmedRisk = 'confirmed_risk';
}
