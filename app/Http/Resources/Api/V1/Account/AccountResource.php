<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use Account\Domain\Account\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Defines the account information that is safe for a customer to view.
 *
 * @mixin Account
 */
final class AccountResource extends JsonResource
{
    /** @return array<string, string|null> */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;

        return [
            'id' => $account->id()->value(),
            'number' => $account->number()?->value(),
            'type' => $account->type()->value,
            'currency' => $account->currency()->value(),
            'status' => $account->status()->value,
            'created_at' => $account->createdAt()->format(DATE_ATOM),
        ];
    }
}
