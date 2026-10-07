<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reporting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Reporting\Application\Customer\View\CustomerAccountView;
use Reporting\Application\Customer\View\CustomerAddressView;
use Reporting\Application\Customer\View\CustomerContactView;
use Reporting\Application\Customer\View\CustomerReportView;

/**
 * Converts the internal customer report into a stable API document.
 *
 * @mixin CustomerReportView
 */
final class CustomerReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CustomerReportView $report */
        $report = $this->resource;

        return [
            'customer_id' => $report->customerId,
            'user_id' => $report->userId,
            'full_name' => $report->fullName(),
            'first_name' => $report->firstName,
            'middle_name' => $report->middleName,
            'last_name' => $report->lastName,
            'date_of_birth' => $report->dateOfBirth->format('Y-m-d'),
            'registered_at' => $report->registeredAt->format(DATE_ATOM),
            'account_count' => $report->accountCount(),
            'addresses' => array_map(
                fn (CustomerAddressView $address): array => [
                    'type' => $address->type,
                    'line_one' => $address->lineOne,
                    'line_two' => $address->lineTwo,
                    'city' => $address->city,
                    'state_or_region' => $address->stateOrRegion,
                    'postal_code' => $address->postalCode,
                    'country_code' => $address->countryCode,
                ],
                $report->addresses
            ),
            'contacts' => array_map(
                fn (CustomerContactView $contact): array => [
                    'type' => $contact->type,
                    'value' => $contact->value,
                ],
                $report->contacts
            ),
            'accounts' => array_map(
                fn (CustomerAccountView $account): array => [
                    'id' => $account->id,
                    'number' => $account->number,
                    'type' => $account->type,
                    'currency' => $account->currency,
                    'status' => $account->status,
                    'opened_on' => $account->openedOn->format(DATE_ATOM),
                ],
                $report->accounts
            ),
        ];
    }
}
