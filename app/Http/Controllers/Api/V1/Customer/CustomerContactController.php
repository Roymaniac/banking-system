<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CustomerContactRequest;
use App\Http\Support\Api\V1\CurrentCustomer;
use Customer\Application\Contact\AddCustomerContact;
use Customer\Application\Contact\AddCustomerContactCommand;
use Customer\Application\Contact\UpdateCustomerContact;
use Customer\Application\Contact\UpdateCustomerContactCommand;
use Customer\Domain\Customer\Contact\ValueObject\ContactId;
use Customer\Domain\Customer\Contact\ValueObject\ContactType;
use Customer\Domain\Customer\Exception\CustomerContactNotFound;
use Customer\Domain\Customer\Exception\DuplicateCustomerContact;
use Illuminate\Http\JsonResponse;

/** Adds and replaces contact methods owned by the signed-in customer. */
final class CustomerContactController extends Controller
{
    public function store(
        CustomerContactRequest $request,
        CurrentCustomer $currentCustomer,
        AddCustomerContact $addContact,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        try {
            $contactId = $addContact->handle(
                new AddCustomerContactCommand(
                    customerId: $customer->id(),
                    type: ContactType::from($request->string('type')->toString()),
                    value: $request->string('value')->toString(),
                )
            );
        } catch (DuplicateCustomerContact $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => ['contact_id' => $contactId->value()]], 201);
    }

    public function update(
        CustomerContactRequest $request,
        string $contact,
        CurrentCustomer $currentCustomer,
        UpdateCustomerContact $updateContact,
    ): JsonResponse {

        $customer = $currentCustomer->profile($request);

        if ($customer === null) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        try {
            $updateContact->handle(
                new UpdateCustomerContactCommand(
                    customerId: $customer->id(),
                    contactId: new ContactId($contact),
                    type: ContactType::from($request->string('type')->toString()),
                    value: $request->string('value')->toString(),
                )
            );
        } catch (CustomerContactNotFound $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        } catch (DuplicateCustomerContact $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['message' => 'Customer contact updated successfully.']);
    }
}
