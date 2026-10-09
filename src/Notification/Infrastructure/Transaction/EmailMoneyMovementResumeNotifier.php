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
use Transaction\Application\Control\MoneyMovementResumeNotifier;
use Transaction\Application\Control\MoneyMovementResumeRequest;

/** Queues privacy-safe approval alerts for active and verified reviewers. */
final readonly class EmailMoneyMovementResumeNotifier implements MoneyMovementResumeNotifier
{
    public function __construct(
        private ConnectionInterface $connection,
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
        private ConfigRepository $config,
    ) {}

    public function pending(MoneyMovementResumeRequest $request): void
    {
        foreach ($this->reviewerEmails($request) as $email) {
            $message = $this->templates->render(
                EmailTemplate::MoneyMovementResumeApproval,
                new RecipientEmail($email),
                [
                    'requestId' => $request->id->value(),
                    'expiresAt' => $request->expiresAt->format('Y-m-d H:i T'),
                    'reviewUrl' => (string) $this->config->get('notification.operations_url'),
                ],
            );

            $this->emails->send($message);
        }
    }

    /** @return list<string> */
    private function reviewerEmails(MoneyMovementResumeRequest $request): array
    {
        return $this->connection->table('users')
            ->join('staff', 'staff.user_id', '=', 'users.identity_user_id')
            ->join('staff_role_assignments', 'staff_role_assignments.staff_id', '=', 'staff.id')
            ->join('administration_roles', 'administration_roles.id', '=', 'staff_role_assignments.role_id')
            ->join('role_permission_assignments', 'role_permission_assignments.role_id', '=', 'administration_roles.id')
            ->join('administration_permissions', 'administration_permissions.id', '=', 'role_permission_assignments.permission_id')
            ->where('administration_permissions.name', 'money_movement.approve')
            ->where('staff.status', StaffStatus::Active->value)
            ->where('administration_roles.status', RoleStatus::Active->value)
            ->whereNotNull('users.email_verified_at')
            ->where('users.identity_user_id', '<>', $request->requestedBy->value())
            ->orderBy('users.email')
            ->distinct()
            ->pluck('users.email')
            ->map(static fn (mixed $email): string => (string) $email)
            ->values()
            ->all();
    }
}
