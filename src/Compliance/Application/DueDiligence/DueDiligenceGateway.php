<?php

declare(strict_types=1);

namespace Compliance\Application\DueDiligence;

use Compliance\Domain\DueDiligence\DocumentType;
use Compliance\Domain\DueDiligence\RiskLevel;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Identity\Domain\User\ValueObject\UserId;
use Shared\Domain\Identifier\Uuid;

interface DueDiligenceGateway
{
    public function submit(
        CustomerId $customerId,
        UserId $actorId,
        DocumentType $documentType,
        string $issuingCountry,
        string $documentNumber,
        DateTimeImmutable $submittedAt,
    ): DueDiligenceView;

    public function findForCustomer(CustomerId $customerId): ?DueDiligenceView;

    /** @return array{items: list<DueDiligenceView>, pagination: array<string, int>} */
    public function reviewQueue(int $page, int $perPage): array;

    public function approve(Uuid $caseId, UserId $reviewerId, RiskLevel $riskLevel, DateTimeImmutable $reviewedAt): DueDiligenceView;

    public function reject(Uuid $caseId, UserId $reviewerId, string $reason, DateTimeImmutable $reviewedAt): DueDiligenceView;

    public function isApproved(CustomerId $customerId): bool;
}
