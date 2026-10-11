<?php

declare(strict_types=1);

namespace Compliance\Domain\DueDiligence;

enum RiskLevel: string
{
    case Low = 'low';
    case Standard = 'standard';
    case High = 'high';
}
