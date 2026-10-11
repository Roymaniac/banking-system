<?php

declare(strict_types=1);

namespace Compliance\Domain\DueDiligence;

enum DocumentType: string
{
    case Passport = 'passport';
    case NationalIdentity = 'national_identity';
    case DriversLicence = 'drivers_licence';
}
