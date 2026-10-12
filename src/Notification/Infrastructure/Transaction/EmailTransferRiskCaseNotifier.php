<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Transaction;

use Administration\Domain\Role\ValueObject\RoleStatus;
use Administration\Domain\Staff\ValueObject\StaffStatus;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Transaction\Application\Risk\TransferRiskCase;
use Transaction\Application\Risk\TransferRiskCaseNotifier;

/** Queues privacy-safe notices for every active authorized risk reviewer. */
final readonly class EmailTransferRiskCaseNotifier implements TransferRiskCaseNotifier
{
    public function __construct(
        private ConnectionInterface $connection,
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
        private ConfigRepository $config,
    ) {}

    public function opened(TransferRiskCase $case): int
    {
        return $this->notify($case, EmailTemplate::TransferRiskCaseOpened, 'A new case is awaiting review.');
    }

    public function reminder(TransferRiskCase $case): int
    {
        return $this->notify($case, EmailTemplate::TransferRiskCaseReminder, 'The review deadline is approaching.');
    }

    public function escalated(TransferRiskCase $case): int
    {
        return $this->notify($case, EmailTemplate::TransferRiskCaseEscalated, 'The review deadline has passed.');
    }

    private function notify(TransferRiskCase $case, EmailTemplate $template, string $notice): int
    {
        $emails = $this->reviewerEmails();

        foreach ($emails as $email) {
            $this->emails->send($this->templates->render(
                $template,
                new RecipientEmail($email),
                [
                    'caseId' => $case->id->value(),
                    'accountLastFour' => $case->accountLastFour,
                    'dueAt' => $case->dueAt->format('Y-m-d H:i T'),
                    'notice' => $notice,
                    'reviewUrl' => (string) $this->config->get('notification.risk_cases_url'),
                ],
            ));
        }

        return count($emails);
    }

    /** @return list<string> */
    private function reviewerEmails(): array
    {
        return $this->connection->table('users')
            ->join('staff', 'staff.user_id', '=', 'users.identity_user_id')
            ->join('staff_role_assignments', 'staff_role_assignments.staff_id', '=', 'staff.id')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->join('role_permission_assignments', 'role_permission_assignments.role_id', '=', 'administration_roles.id')
            ->join('administration_permissions', 'administration_permissions.id', '=', 'role_permission_assignments.permission_id')
            ->where('administration_permissions.name', 'risk_cases.review')
            ->where('staff.status', StaffStatus::Active->value)
            ->where('administration_roles.status', RoleStatus::Active->value)
            ->whereNotNull('users.email_verified_at')
            ->orderBy('users.email')
            ->distinct()
            ->pluck('users.email')
            ->map(static fn (mixed $email): string => (string) $email)
            ->values()
            ->all();
    }
}
