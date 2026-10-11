<?php

declare(strict_types=1);

namespace Compliance\Domain\DueDiligence;

enum DueDiligenceStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
