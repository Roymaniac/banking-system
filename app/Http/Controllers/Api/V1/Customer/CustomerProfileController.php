<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateCustomerProfileRequest;
use App\Http\Resources\Api\V1\Customer\CustomerProfileResource;
use App\Models\User as LaravelUser;
use Customer\Application\Profile\CreateCustomerProfile;
use Customer\Application\Profile\CreateCustomerProfileCommand;
use Customer\Domain\Customer\Exception\CustomerProfileAlreadyExists;
use Customer\Domain\Customer\Exception\UserNotEligibleForCustomerProfile;
use Customer\Domain\Customer\Repository\CustomerRepository;
use Identity\Domain\User\ValueObject\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Exposes the signed-in user's own customer profile to the API. */
final class CustomerProfileController extends Controller
{
    public function store(
        CreateCustomerProfileRequest $request,
        CreateCustomerProfile $createProfile,
    ): JsonResponse {
        try {
            $customer = $createProfile->handle(
                new CreateCustomerProfileCommand(
                    userId: $this->userId($request),
                    firstName: $request->string('first_name')->toString(),
                    middleName: $this->optionalString($request, 'middle_name'),
                    lastName: $request->string('last_name')->toString(),
                    dateOfBirth: $request->string('date_of_birth')->toString(),
                )
            );
        } catch (CustomerProfileAlreadyExists $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (UserNotEligibleForCustomerProfile $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json([
            'data' => ['customer' => new CustomerProfileResource($customer)],
        ], 201);
    }

    public function show(Request $request, CustomerRepository $customers): JsonResponse
    {
        $customer = $customers->findByUserId($this->userId($request));

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        return response()->json([
            'data' => ['customer' => new CustomerProfileResource($customer)],
        ]);
    }

    private function userId(Request $request): UserId
    {
        /** @var LaravelUser $user */
        $user = $request->user();

        return new UserId((string) $user->identity_user_id);
    }

    private function optionalString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }
}
