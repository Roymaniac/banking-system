<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Operation;

use Account\Application\Provisioning\ProvisionedAccount;
use App\Http\Resources\Api\V1\Account\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Confirms the account and authoritative ledger created by provisioning.
 *
 * @mixin ProvisionedAccount
 */
final class AccountProvisioningResource extends JsonResource
{
    public function __construct(ProvisionedAccount $result)
    {
        parent::__construct($result);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ProvisionedAccount $result */
        $result = $this->resource;

        return [
            'account' => new AccountResource($result->account),
            'ledger' => [
                'id' => $result->ledger->id()->value(),
                'account_id' => $result->ledger->accountId()->value(),
                'currency' => $result->ledger->currency()->value(),
                'created_at' => $result->ledger->createdAt()->format(DATE_ATOM),
            ],
        ];
    }
}
