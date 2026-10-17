<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use Identity\Application\Authorization\AuthorizeUser;
use Identity\Application\Authorization\AuthorizeUserCommand;
use Identity\Domain\Authorization\ValueObject\Permission;
use Illuminate\Http\Request;

/** Applies the existing action permission to each account lookup purpose. */
final readonly class OperatorLookupAccess
{
    private const ACCOUNT_PERMISSIONS = [
        'provision' => 'accounts.provision',
        'freeze' => 'accounts.freeze',
        'unfreeze' => 'accounts.freeze',
        'close' => 'accounts.close',
        'deposit' => 'transactions.deposit',
        'withdrawal' => 'transactions.withdraw',
        'transaction-report' => 'transaction_reports.view',
        'audit' => 'audit.view',
    ];

    public function __construct(
        private AuthorizeUser $authorize,
        private CurrentIdentity $identity,
    ) {}

    public function account(Request $request, string $purpose): void
    {
        $this->authorize->handle(new AuthorizeUserCommand(
            $this->identity->userId($request),
            new Permission(self::ACCOUNT_PERMISSIONS[$purpose]),
        ));
    }
}
