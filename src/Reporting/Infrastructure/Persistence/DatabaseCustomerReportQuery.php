<?php

declare(strict_types=1);

namespace Reporting\Infrastructure\Persistence;

use Customer\Domain\Customer\ValueObject\CustomerId;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Reporting\Application\Customer\CustomerReportQuery;
use Reporting\Application\Customer\View\CustomerAccountView;
use Reporting\Application\Customer\View\CustomerAddressView;
use Reporting\Application\Customer\View\CustomerContactView;
use Reporting\Application\Customer\View\CustomerReportView;

/** Reads customer reporting data directly without rebuilding domain aggregates. */
final readonly class DatabaseCustomerReportQuery implements CustomerReportQuery
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function find(CustomerId $customerId): ?CustomerReportView
    {
        $customer = $this->connection->table('customers')
            ->where('id', $customerId->value())
            ->first();

        if ($customer === null) {
            return null;
        }

        $addresses = $this->connection->table('customer_addresses')
            ->where('customer_id', $customerId->value())
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (object $address): CustomerAddressView => new CustomerAddressView(
                $address->type,
                $address->line_one,
                $address->line_two,
                $address->city,
                $address->state_or_region,
                $address->postal_code,
                $address->country_code,
            ))
            ->all();

        $contacts = $this->connection->table('customer_contacts')
            ->where('customer_id', $customerId->value())
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (object $contact): CustomerContactView => new CustomerContactView(
                $contact->type,
                $contact->value,
            ))
            ->all();

        $accounts = $this->connection->table('accounts')
            ->where('customer_id', $customerId->value())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $account): CustomerAccountView => new CustomerAccountView(
                $account->id,
                $account->number,
                $account->type,
                $account->currency,
                $account->status,
                new DateTimeImmutable($account->created_at),
            ))
            ->all();

        return new CustomerReportView(
            $customer->id,
            $customer->user_id,
            $customer->first_name,
            $customer->middle_name,
            $customer->last_name,
            new DateTimeImmutable($customer->date_of_birth),
            new DateTimeImmutable($customer->registered_at),
            $addresses,
            $contacts,
            $accounts,
        );
    }
}
