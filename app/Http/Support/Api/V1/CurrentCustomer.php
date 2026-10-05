<?php

declare(strict_types=1);

namespace App\Http\Support\Api\V1;

use App\Models\User;
use Customer\Domain\Customer\Customer;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\Request;

/** Resolves customer data from the authenticated user, never from public input. */
final readonly class CurrentCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function userId(Request $request): UserId
    {
        /** @var User $user */
        $user = $request->user();

        return new UserId((string) $user->identity_user_id);
    }

    public function profile(Request $request): ?Customer
    {
        return $this->customers->findByUserId($this->userId($request));
    }
}
