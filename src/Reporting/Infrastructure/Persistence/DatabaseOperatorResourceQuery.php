<?php

declare(strict_types=1);

namespace Reporting\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Reporting\Application\Operator\OperatorResourceQuery;

/** Builds minimal, bounded selector records without exposing writable models. */
final readonly class DatabaseOperatorResourceQuery implements OperatorResourceQuery
{
    public function __construct(private ConnectionInterface $connection) {}

    public function accounts(array $filters): array
    {
        $query = $this->accountQuery();

        if (is_string($filters['customer_id'])) {
            $query->where('accounts.customer_id', $filters['customer_id']);
        }
        if (is_string($filters['status'])) {
            $query->where('accounts.status', $filters['status']);
        }
        if (is_string($filters['currency'])) {
            $query->where('accounts.currency', $filters['currency']);
        }
        if (is_string($filters['type'])) {
            $query->where('accounts.type', $filters['type']);
        }
        if (is_string($filters['search'])) {
            $this->searchAccounts($query, $filters['search']);
        }

        $purpose = (string) $filters['purpose'];

        return $this->paginate(
            $query->orderByDesc('accounts.created_at')->orderBy('accounts.id'),
            (int) $filters['page'],
            (int) $filters['per_page'],
            fn (object $row): array => $this->accountRow($row, $purpose),
        );
    }

    public function account(string $id, string $purpose): ?array
    {
        $row = $this->accountQuery()->where('accounts.id', $id)->first();

        return $row === null ? null : $this->accountRow($row, $purpose);
    }

    public function customers(array $filters): array
    {
        $query = $this->connection->table('customers')
            ->select('customers.*');
        $this->excludeSettlementCustomers($query);

        if (is_string($filters['search'])) {
            $this->searchCustomers($query, $filters['search']);
        }

        return $this->paginate(
            $query->orderBy('customers.last_name')->orderBy('customers.first_name')->orderBy('customers.id'),
            (int) $filters['page'],
            (int) $filters['per_page'],
            fn (object $row): array => $this->customerRow($row),
        );
    }

    public function customer(string $id): ?array
    {
        $query = $this->connection->table('customers')->where('id', $id);
        $this->excludeSettlementCustomers($query);
        $row = $query->first();

        if ($row === null) {
            return null;
        }

        return [
            ...$this->customerRow($row),
            'account_count' => $this->connection->table('accounts')->where('customer_id', $id)->count(),
            'registered_at' => $this->date($row->registered_at),
        ];
    }

    public function staffCandidates(array $filters): array
    {
        $query = $this->connection->table('users')
            ->leftJoin('staff', 'staff.user_id', '=', 'users.identity_user_id')
            ->whereNotNull('users.identity_user_id')
            ->whereNotNull('users.email_verified_at')
            ->whereNull('staff.id')
            ->select('users.identity_user_id', 'users.name', 'users.email', 'users.email_verified_at', 'staff.id as staff_id');
        $this->excludeSettlementUsers($query);

        if (is_string($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('LOWER(users.email) = ?', [$term])
                    ->orWhereRaw('LOWER(users.name) LIKE ?', [$this->prefix($term)])
                    ->orWhere('users.identity_user_id', $this->identityFromReference($term));
            });
        }

        return $this->paginate(
            $query->orderBy('users.name')->orderBy('users.identity_user_id'),
            (int) $filters['page'],
            (int) $filters['per_page'],
            fn (object $row): array => $this->staffCandidateRow($row),
        );
    }

    public function staffCandidate(string $userId): ?array
    {
        $query = $this->connection->table('users')
            ->leftJoin('staff', 'staff.user_id', '=', 'users.identity_user_id')
            ->where('users.identity_user_id', $userId)
            ->select('users.identity_user_id', 'users.name', 'users.email', 'users.email_verified_at', 'staff.id as staff_id');
        $this->excludeSettlementUsers($query);
        $row = $query->first();

        return $row === null ? null : $this->staffCandidateRow($row);
    }

    public function ledgerEntries(array $filters): array
    {
        $query = $this->ledgerEntryQuery();

        if (is_string($filters['account_id'])) {
            $query->where('accounts.id', $filters['account_id']);
        }
        if (is_string($filters['customer_id'])) {
            $query->where('customers.id', $filters['customer_id']);
        }
        if (is_string($filters['from'])) {
            $query->where('ledger_entries.occurred_at', '>=', $filters['from'].' 00:00:00');
        }
        if (is_string($filters['to'])) {
            $exclusiveEnd = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d 00:00:00');
            $query->where('ledger_entries.occurred_at', '<', $exclusiveEnd);
        }
        if (is_string($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('LOWER(ledger_entries.reference) LIKE ?', [$this->prefix($term)])
                    ->orWhere('ledger_entries.id', $this->entryFromReference($term));
            });
        }

        return $this->paginate(
            $query->orderByDesc('ledger_entries.occurred_at')->orderBy('ledger_entries.id'),
            (int) $filters['page'],
            (int) $filters['per_page'],
            fn (object $row): array => $this->ledgerEntryRow($row),
        );
    }

    public function ledgerEntry(string $id): ?array
    {
        $row = $this->ledgerEntryQuery()->where('ledger_entries.id', $id)->first();

        if ($row === null) {
            return null;
        }

        $postings = $this->connection->table('ledger_postings')
            ->leftJoin('ledgers', 'ledgers.id', '=', 'ledger_postings.ledger_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'ledgers.account_id')
            ->where('ledger_postings.entry_id', $id)
            ->orderBy('ledger_postings.id')
            ->get([
                'ledger_postings.ledger_id',
                'ledger_postings.side',
                'ledger_postings.minor_units',
                'ledger_postings.currency',
                'accounts.number as account_number',
            ])
            ->map(fn (object $posting): array => [
                'ledger_id' => $posting->ledger_id,
                'side' => $posting->side,
                'minor_units' => (int) $posting->minor_units,
                'currency' => $posting->currency,
                'account_last_four' => $this->lastFour($posting->account_number),
            ])
            ->all();

        return [...$this->ledgerEntryRow($row), 'postings' => $postings];
    }

    private function accountQuery(): Builder
    {
        $query = $this->connection->table('accounts')
            ->join('customers', 'customers.id', '=', 'accounts.customer_id')
            ->leftJoin('ledgers', 'ledgers.account_id', '=', 'accounts.id')
            ->leftJoin('ledger_balances', 'ledger_balances.ledger_id', '=', 'ledgers.id')
            ->leftJoin('customer_due_diligence_cases', 'customer_due_diligence_cases.customer_id', '=', 'customers.id')
            ->select([
                'accounts.*',
                'customers.first_name',
                'customers.middle_name',
                'customers.last_name',
                'ledgers.id as ledger_id',
                'ledger_balances.balance_minor_units',
                'customer_due_diligence_cases.status as due_diligence_status',
            ]);

        $query->where('accounts.is_internal', false);

        return $query;
    }

    private function ledgerEntryQuery(): Builder
    {
        return $this->connection->table('ledger_entries')
            ->join('ledgers', 'ledgers.id', '=', 'ledger_entries.ledger_id')
            ->join('accounts', 'accounts.id', '=', 'ledgers.account_id')
            ->join('customers', 'customers.id', '=', 'accounts.customer_id')
            ->leftJoin('deposits', 'deposits.ledger_entry_id', '=', 'ledger_entries.id')
            ->leftJoin('withdrawals', 'withdrawals.ledger_entry_id', '=', 'ledger_entries.id')
            ->leftJoin('transfers', 'transfers.ledger_entry_id', '=', 'ledger_entries.id')
            ->leftJoin('multiple_transfers', 'multiple_transfers.ledger_entry_id', '=', 'ledger_entries.id')
            ->leftJoin('transaction_reversals', 'transaction_reversals.original_ledger_entry_id', '=', 'ledger_entries.id')
            ->where('ledger_entries.status', 'posted')
            ->where('accounts.is_internal', false)
            ->select([
                'ledger_entries.id',
                'ledger_entries.reference',
                'ledger_entries.description',
                'ledger_entries.occurred_at',
                'ledger_entries.status',
                'accounts.id as account_id',
                'accounts.number as account_number',
                'customers.id as customer_id',
                'customers.first_name',
                'customers.middle_name',
                'customers.last_name',
                'transaction_reversals.reference as reversal_reference',
            ])
            ->selectRaw('COALESCE(deposits.minor_units, withdrawals.minor_units, transfers.minor_units, multiple_transfers.total_minor_units, (SELECT MAX(lp.minor_units) FROM ledger_postings lp WHERE lp.entry_id = ledger_entries.id)) AS minor_units')
            ->selectRaw('COALESCE(deposits.currency, withdrawals.currency, transfers.currency, multiple_transfers.currency, (SELECT MIN(lp.currency) FROM ledger_postings lp WHERE lp.entry_id = ledger_entries.id)) AS currency')
            ->selectRaw("CASE WHEN deposits.id IS NOT NULL THEN 'deposit' WHEN withdrawals.id IS NOT NULL THEN 'withdrawal' WHEN transfers.id IS NOT NULL THEN 'transfer' WHEN multiple_transfers.id IS NOT NULL THEN 'multiple_transfer' ELSE 'ledger_entry' END AS transaction_type");
    }

    /** @return array<string, mixed> */
    private function accountRow(object $row, string $purpose): array
    {
        [$eligible, $reason] = $this->accountEligibility($row, $purpose);
        $customerName = $this->customerName($row);
        $lastFour = $this->lastFour($row->number);
        $accountLabel = $lastFour === null ? 'Pending account application' : 'Account ending '.$lastFour;

        return [
            'id' => $row->id,
            'display_label' => "{$customerName} · {$accountLabel} · {$row->currency}",
            'reference' => $this->accountReference($row->id),
            'customer' => [
                'id' => $row->customer_id,
                'display_name' => $customerName,
                'reference' => $this->customerReference($row->customer_id),
            ],
            'account_last_four' => $lastFour,
            'type' => $row->type,
            'currency' => $row->currency,
            'status' => $row->status,
            'eligible' => $eligible,
            'ineligibility_reason_code' => $reason,
            'version' => (int) $row->version,
            'created_at' => $this->date($row->created_at),
        ];
    }

    /** @return array{bool, string|null} */
    private function accountEligibility(object $row, string $purpose): array
    {
        return match ($purpose) {
            'provision' => match (true) {
                $row->status !== 'pending' => [false, 'account_not_pending'],
                $row->number !== null => [false, 'account_number_already_assigned'],
                $row->ledger_id !== null => [false, 'ledger_already_exists'],
                $row->due_diligence_status !== 'approved' => [false, 'due_diligence_not_approved'],
                default => [true, null],
            },
            'freeze' => $row->status === 'active' ? [true, null] : [false, 'account_not_active'],
            'unfreeze' => $row->status === 'frozen' ? [true, null] : [false, 'account_not_frozen'],
            'close' => ! in_array($row->status, ['active', 'frozen'], true)
                ? [false, 'account_not_closable']
                : (((int) ($row->balance_minor_units ?? 0)) === 0
                    ? [true, null]
                    : [false, 'account_balance_not_zero']),
            'deposit', 'withdrawal' => match (true) {
                $row->status !== 'active' => [false, 'account_not_active'],
                $row->ledger_id === null => [false, 'ledger_unavailable'],
                default => [true, null],
            },
            'transaction-report' => $row->ledger_id === null
                ? [false, 'ledger_unavailable']
                : [true, null],
            'audit' => [true, null],
            default => [false, 'unsupported_purpose'],
        };
    }

    /** @return array<string, mixed> */
    private function customerRow(object $row): array
    {
        $name = $this->customerName($row);
        $contact = $this->connection->table('customer_contacts')
            ->where('customer_id', $row->id)
            ->orderByRaw("CASE WHEN type = 'email' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first(['type', 'value']);

        return [
            'id' => $row->id,
            'display_name' => $name,
            'display_label' => $contact === null ? $name : $name.' · '.$this->maskContact($contact->type, $contact->value),
            'reference' => $this->customerReference($row->id),
            'masked_contact' => $contact === null ? null : $this->maskContact($contact->type, $contact->value),
        ];
    }

    /** @return array<string, mixed> */
    private function staffCandidateRow(object $row): array
    {
        $reason = match (true) {
            $row->email_verified_at === null => 'email_not_verified',
            $row->staff_id !== null => 'already_staff',
            default => null,
        };

        return [
            'user_id' => $row->identity_user_id,
            'display_name' => $row->name,
            'display_label' => $row->name.' · '.$this->maskEmail($row->email),
            'masked_email' => $this->maskEmail($row->email),
            'eligible' => $reason === null,
            'ineligibility_reason_code' => $reason,
        ];
    }

    /** @return array<string, mixed> */
    private function ledgerEntryRow(object $row): array
    {
        $name = $this->customerName($row);
        $lastFour = $this->lastFour($row->account_number);
        $reversible = $row->reversal_reference === null;

        return [
            'entry_id' => $row->id,
            'display_label' => $row->reference.' · '.$name.' · '.($lastFour === null ? 'Pending account' : 'Account ending '.$lastFour),
            'reference' => $row->reference,
            'occurred_at' => $this->date($row->occurred_at),
            'description' => $row->description,
            'transaction_type' => $row->transaction_type,
            'status' => $row->status,
            'minor_units' => (int) $row->minor_units,
            'currency' => $row->currency,
            'account' => [
                'id' => $row->account_id,
                'account_last_four' => $lastFour,
            ],
            'customer' => [
                'id' => $row->customer_id,
                'display_name' => $name,
                'reference' => $this->customerReference($row->customer_id),
            ],
            'reversible' => $reversible,
            'ineligibility_reason_code' => $reversible ? null : 'already_reversed',
            'existing_reversal_reference' => $row->reversal_reference,
        ];
    }

    private function searchAccounts(Builder $query, string $search): void
    {
        $term = mb_strtolower(trim($search));
        $identity = $this->accountFromReference($term);
        $tokens = $this->nameTokens($term);

        $query->where(function (Builder $query) use ($term, $identity, $tokens): void {
            $query->where('accounts.id', $identity)
                ->orWhereRaw('LOWER(accounts.number) = ?', [$term]);

            foreach ($tokens as $token) {
                $query->orWhereRaw('LOWER(customers.first_name) LIKE ?', [$this->prefix($token)])
                    ->orWhereRaw('LOWER(customers.middle_name) LIKE ?', [$this->prefix($token)])
                    ->orWhereRaw('LOWER(customers.last_name) LIKE ?', [$this->prefix($token)]);
            }
        });
    }

    private function excludeSettlementCustomers(Builder $query): void
    {
        $query->whereNotExists(function (Builder $settlement): void {
            $settlement->selectRaw('1')
                ->from('accounts as settlement_accounts')
                ->whereColumn('settlement_accounts.customer_id', 'customers.id')
                ->where('settlement_accounts.is_internal', true);
        });
    }

    private function excludeSettlementUsers(Builder $query): void
    {
        $query->whereNotExists(function (Builder $settlement): void {
            $settlement->selectRaw('1')
                ->from('customers as settlement_customers')
                ->join('accounts as settlement_accounts', 'settlement_accounts.customer_id', '=', 'settlement_customers.id')
                ->whereColumn('settlement_customers.user_id', 'users.identity_user_id')
                ->where('settlement_accounts.is_internal', true);
        });
    }

    private function searchCustomers(Builder $query, string $search): void
    {
        $term = mb_strtolower(trim($search));
        $identity = $this->customerFromReference($term);
        $tokens = $this->nameTokens($term);

        $query->where(function (Builder $query) use ($term, $identity, $tokens): void {
            $query->where('customers.id', $identity)
                ->orWhereExists(function (Builder $contact) use ($term): void {
                    $contact->selectRaw('1')
                        ->from('customer_contacts')
                        ->whereColumn('customer_contacts.customer_id', 'customers.id')
                        ->whereRaw('LOWER(customer_contacts.value) = ?', [$term]);
                })
                ->orWhereExists(function (Builder $account) use ($term): void {
                    $account->selectRaw('1')
                        ->from('accounts')
                        ->whereColumn('accounts.customer_id', 'customers.id')
                        ->whereRaw('LOWER(accounts.number) = ?', [$term]);
                });

            foreach ($tokens as $token) {
                $query->orWhereRaw('LOWER(customers.first_name) LIKE ?', [$this->prefix($token)])
                    ->orWhereRaw('LOWER(customers.middle_name) LIKE ?', [$this->prefix($token)])
                    ->orWhereRaw('LOWER(customers.last_name) LIKE ?', [$this->prefix($token)]);
            }
        });
    }

    /** @return list<string> */
    private function nameTokens(string $term): array
    {
        return array_values(array_filter(
            preg_split('/[^\pL\pN]+/u', $term) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 2,
        ));
    }

    private function prefix(string $term): string
    {
        return str_replace(['%', '_'], ['', ''], $term).'%';
    }

    private function accountFromReference(string $value): string
    {
        return $this->uuidFromReference($value, 'account-');
    }

    private function customerFromReference(string $value): string
    {
        return $this->uuidFromReference($value, 'customer-');
    }

    private function identityFromReference(string $value): string
    {
        return $this->uuidFromReference($value, 'user-');
    }

    private function entryFromReference(string $value): string
    {
        return $this->uuidFromReference($value, 'entry-');
    }

    private function uuidFromReference(string $value, string $prefix): string
    {
        $compact = str_starts_with($value, $prefix) ? substr($value, strlen($prefix)) : $value;
        $compact = str_replace('-', '', $compact);

        if (preg_match('/^[0-9a-f]{32}$/', $compact) !== 1) {
            return '00000000-0000-0000-0000-000000000000';
        }

        return substr($compact, 0, 8).'-'.substr($compact, 8, 4).'-'.substr($compact, 12, 4).'-'.substr($compact, 16, 4).'-'.substr($compact, 20);
    }

    private function accountReference(string $id): string
    {
        return 'ACCOUNT-'.strtoupper(str_replace('-', '', $id));
    }

    private function customerReference(string $id): string
    {
        return 'CUSTOMER-'.strtoupper(str_replace('-', '', $id));
    }

    private function customerName(object $row): string
    {
        return implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name]));
    }

    private function lastFour(?string $number): ?string
    {
        return $number === null ? null : substr($number, -4);
    }

    private function maskContact(string $type, string $value): string
    {
        return $type === 'email' ? $this->maskEmail($value) : '••••'.substr($value, -4);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'•••@'.$domain;
    }

    /**
     * @param  callable(object): array<string, mixed>  $transform
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    private function paginate(Builder $query, int $page, int $perPage, callable $transform): array
    {
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map($transform)->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function date(string $value): string
    {
        return (new DateTimeImmutable($value))->format(DATE_ATOM);
    }
}
