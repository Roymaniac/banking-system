<?php

declare(strict_types=1);

namespace Compliance\Infrastructure\Persistence;

use Compliance\Application\DueDiligence\DueDiligenceGateway;
use Compliance\Application\DueDiligence\DueDiligenceView;
use Compliance\Domain\DueDiligence\DocumentType;
use Compliance\Domain\DueDiligence\DueDiligenceStatus;
use Compliance\Domain\DueDiligence\Exception\DuplicateIdentityDocument;
use Compliance\Domain\DueDiligence\Exception\InvalidDueDiligenceAction;
use Compliance\Domain\DueDiligence\RiskLevel;
use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use DateTimeZone;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Shared\Domain\Identifier\Uuid;
use Shared\Domain\Identifier\UuidGenerator;

/** Stores encrypted identity evidence and an append-only decision history. */
final readonly class DatabaseDueDiligenceGateway implements DueDiligenceGateway
{
    public function __construct(
        private ConnectionInterface $connection,
        private Encrypter $encrypter,
        private UuidGenerator $ids,
    ) {}

    public function submit(
        CustomerId $customerId,
        UserId $actorId,
        DocumentType $documentType,
        string $issuingCountry,
        string $documentNumber,
        DateTimeImmutable $submittedAt,
    ): DueDiligenceView {
        try {
            return $this->connection->transaction(function () use (
                $customerId,
                $actorId,
                $documentType,
                $issuingCountry,
                $documentNumber,
                $submittedAt,
            ): DueDiligenceView {
                $existing = $this->connection->table('customer_due_diligence_cases')
                    ->where('customer_id', $customerId->value())
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && (string) $existing->status !== DueDiligenceStatus::Rejected->value) {
                    throw InvalidDueDiligenceAction::because(
                        'Identity evidence can only be resubmitted after a rejection.',
                    );
                }

                $caseId = $existing === null ? $this->ids->generate()->value() : (string) $existing->id;
                $normalizedDocument = strtoupper(trim($documentNumber));
                $values = [
                    'status' => DueDiligenceStatus::Submitted->value,
                    'document_type' => $documentType->value,
                    'issuing_country' => strtoupper($issuingCountry),
                    'document_number_encrypted' => $this->encrypter->encryptString($normalizedDocument),
                    'document_fingerprint' => $this->fingerprint(
                        $documentType,
                        $issuingCountry,
                        $normalizedDocument,
                    ),
                    'document_last_four' => substr($normalizedDocument, -4),
                    'risk_level' => null,
                    'submitted_at' => $this->utc($submittedAt),
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'rejection_reason' => null,
                    'version' => $existing === null ? 1 : ((int) $existing->version + 1),
                ];

                if ($existing === null) {
                    $this->connection->table('customer_due_diligence_cases')->insert([
                        'id' => $caseId,
                        'customer_id' => $customerId->value(),
                        ...$values,
                    ]);
                } else {
                    $this->connection->table('customer_due_diligence_cases')
                        ->where('id', $caseId)
                        ->update($values);
                }

                $this->recordEvent(
                    $caseId,
                    $existing === null ? 'submitted' : 'resubmitted',
                    $actorId,
                    null,
                    $submittedAt,
                );

                return $this->findById($caseId);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw DuplicateIdentityDocument::create();
            }

            throw $exception;
        }
    }

    public function findForCustomer(CustomerId $customerId): ?DueDiligenceView
    {
        $row = $this->baseQuery()->where('customer_due_diligence_cases.customer_id', $customerId->value())->first();

        return $row === null ? null : $this->map($row);
    }

    public function reviewQueue(int $page, int $perPage): array
    {
        $query = $this->baseQuery()
            ->where('customer_due_diligence_cases.status', DueDiligenceStatus::Submitted->value);
        $total = (clone $query)->count();
        $items = $query
            ->orderBy('customer_due_diligence_cases.submitted_at')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (object $row): DueDiligenceView => $this->map($row))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function approve(
        Uuid $caseId,
        UserId $reviewerId,
        RiskLevel $riskLevel,
        DateTimeImmutable $reviewedAt,
    ): DueDiligenceView {
        return $this->review(
            $caseId,
            $reviewerId,
            DueDiligenceStatus::Approved,
            $riskLevel,
            null,
            $reviewedAt,
        );
    }

    public function reject(
        Uuid $caseId,
        UserId $reviewerId,
        string $reason,
        DateTimeImmutable $reviewedAt,
    ): DueDiligenceView {
        return $this->review(
            $caseId,
            $reviewerId,
            DueDiligenceStatus::Rejected,
            null,
            trim($reason),
            $reviewedAt,
        );
    }

    public function isApproved(CustomerId $customerId): bool
    {
        return $this->connection->table('customer_due_diligence_cases')
            ->where('customer_id', $customerId->value())
            ->where('status', DueDiligenceStatus::Approved->value)
            ->exists();
    }

    private function review(
        Uuid $caseId,
        UserId $reviewerId,
        DueDiligenceStatus $status,
        ?RiskLevel $riskLevel,
        ?string $reason,
        DateTimeImmutable $reviewedAt,
    ): DueDiligenceView {
        return $this->connection->transaction(function () use (
            $caseId,
            $reviewerId,
            $status,
            $riskLevel,
            $reason,
            $reviewedAt,
        ): DueDiligenceView {
            $case = $this->connection->table('customer_due_diligence_cases')
                ->where('id', $caseId->value())
                ->lockForUpdate()
                ->first();

            if ($case === null) {
                throw InvalidDueDiligenceAction::because('The due-diligence case does not exist.');
            }
            if ((string) $case->status !== DueDiligenceStatus::Submitted->value) {
                throw InvalidDueDiligenceAction::because('Only a submitted due-diligence case can be reviewed.');
            }

            $customerUserId = $this->connection->table('customers')
                ->where('id', $case->customer_id)
                ->value('user_id');
            if ((string) $customerUserId === $reviewerId->value()) {
                throw InvalidDueDiligenceAction::because('A reviewer cannot decide their own due-diligence case.');
            }

            $this->connection->table('customer_due_diligence_cases')
                ->where('id', $caseId->value())
                ->update([
                    'status' => $status->value,
                    'risk_level' => $riskLevel?->value,
                    'reviewed_at' => $this->utc($reviewedAt),
                    'reviewed_by' => $reviewerId->value(),
                    'rejection_reason' => $reason,
                    'version' => (int) $case->version + 1,
                ]);
            $this->recordEvent($caseId->value(), $status->value, $reviewerId, $reason, $reviewedAt);

            return $this->findById($caseId->value());
        });
    }

    private function baseQuery(): Builder
    {
        return $this->connection->table('customer_due_diligence_cases')
            ->join('customers', 'customers.id', '=', 'customer_due_diligence_cases.customer_id')
            ->select(
                'customer_due_diligence_cases.*',
                'customers.first_name',
                'customers.middle_name',
                'customers.last_name',
                'customers.date_of_birth',
            );
    }

    private function findById(string $id): DueDiligenceView
    {
        $row = $this->baseQuery()->where('customer_due_diligence_cases.id', $id)->first();

        if ($row === null) {
            throw new \RuntimeException('The stored due-diligence case could not be loaded.');
        }

        return $this->map($row);
    }

    private function map(object $row): DueDiligenceView
    {
        return new DueDiligenceView(
            id: (string) $row->id,
            customerId: (string) $row->customer_id,
            customerName: trim(implode(' ', array_filter([
                (string) $row->first_name,
                $row->middle_name === null ? null : (string) $row->middle_name,
                (string) $row->last_name,
            ]))),
            dateOfBirth: (string) $row->date_of_birth,
            status: (string) $row->status,
            documentType: (string) $row->document_type,
            issuingCountry: (string) $row->issuing_country,
            documentLastFour: (string) $row->document_last_four,
            riskLevel: $row->risk_level === null ? null : (string) $row->risk_level,
            submittedAt: (string) $row->submitted_at,
            reviewedAt: $row->reviewed_at === null ? null : (string) $row->reviewed_at,
            rejectionReason: $row->rejection_reason === null ? null : (string) $row->rejection_reason,
        );
    }

    private function recordEvent(
        string $caseId,
        string $action,
        UserId $actorId,
        ?string $reason,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->connection->table('customer_due_diligence_events')->insert([
            'id' => $this->ids->generate()->value(),
            'case_id' => $caseId,
            'action' => $action,
            'actor_id' => $actorId->value(),
            'reason' => $reason,
            'occurred_at' => $this->utc($occurredAt),
        ]);
    }

    private function fingerprint(DocumentType $type, string $country, string $number): string
    {
        $key = (string) config('compliance.document_fingerprint_key');

        return hash_hmac('sha256', $type->value.'|'.strtoupper($country).'|'.$number, $key);
    }

    private function utc(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
