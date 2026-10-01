<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerAddressRequest;
use App\Http\Support\Api\V1\CurrentCustomer;
use Customer\Application\Address\AddCustomerAddress;
use Customer\Application\Address\AddCustomerAddressCommand;
use Customer\Application\Address\UpdateCustomerAddress;
use Customer\Application\Address\UpdateCustomerAddressCommand;
use Customer\Domain\Customer\Address\ValueObject\AddressId;
use Customer\Domain\Customer\Address\ValueObject\AddressType;
use Customer\Domain\Customer\Exception\CustomerAddressNotFound;
use Customer\Domain\Customer\Exception\DuplicateCustomerAddress;
use Illuminate\Http\JsonResponse;

/** Adds and replaces addresses owned by the signed-in customer. */
final class CustomerAddressController extends Controller
{
    public function store(
        CustomerAddressRequest $request,
        CurrentCustomer $currentCustomer,
        AddCustomerAddress $addAddress,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        try {
            $addressId = $addAddress->handle(
                new AddCustomerAddressCommand(
                    customerId: $customer->id(),
                    type: AddressType::from($request->string('type')->toString()),
                    lineOne: $request->string('line_one')->toString(),
                    lineTwo: $this->optionalString($request, 'line_two'),
                    city: $request->string('city')->toString(),
                    stateOrRegion: $request->string('state_or_region')->toString(),
                    postalCode: $request->string('postal_code')->toString(),
                    countryCode: $request->string('country_code')->toString(),
                )
            );
        } catch (DuplicateCustomerAddress $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['address_id' => $addressId->value()]], 201);
    }

    public function update(
        CustomerAddressRequest $request,
        string $address,
        CurrentCustomer $currentCustomer,
        UpdateCustomerAddress $updateAddress,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        try {
            $updateAddress->handle(
                new UpdateCustomerAddressCommand(
                    customerId: $customer->id(),
                    addressId: new AddressId($address),
                    type: AddressType::from($request->string('type')->toString()),
                    lineOne: $request->string('line_one')->toString(),
                    lineTwo: $this->optionalString($request, 'line_two'),
                    city: $request->string('city')->toString(),
                    stateOrRegion: $request->string('state_or_region')->toString(),
                    postalCode: $request->string('postal_code')->toString(),
                    countryCode: $request->string('country_code')->toString(),
                )
            );
        } catch (CustomerAddressNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (DuplicateCustomerAddress $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Customer address updated successfully.']);
    }

    private function optionalString(CustomerAddressRequest $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }
}
