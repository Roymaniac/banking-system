<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds bounded-selector indexes without changing any business record. */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            // Internal clearing accounts must never appear in customer/staff selectors.
            $table->boolean('is_internal')->default(false)->index();
        });

        $settlementLedgerIds = $this->configuredSettlementLedgerIds();
        if ($settlementLedgerIds !== []) {
            $accountIds = DB::table('ledgers')->whereIn('id', $settlementLedgerIds)->pluck('account_id');
            DB::table('accounts')->whereIn('id', $accountIds)->update(['is_internal' => true]);
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(['last_name', 'first_name', 'id'], 'customers_operator_name_idx');
        });
        Schema::table('customer_contacts', function (Blueprint $table): void {
            $table->index(['value', 'customer_id'], 'customer_contacts_lookup_idx');
        });
        Schema::table('accounts', function (Blueprint $table): void {
            $table->index(['currency', 'type', 'status', 'id'], 'accounts_operator_filter_idx');
        });
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->index(['status', 'occurred_at', 'id'], 'ledger_entries_reversal_lookup_idx');
            $table->index(['reference', 'id'], 'ledger_entries_reference_lookup_idx');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['name', 'identity_user_id'], 'users_staff_candidate_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_staff_candidate_name_idx');
        });
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('ledger_entries_reversal_lookup_idx');
            $table->dropIndex('ledger_entries_reference_lookup_idx');
        });
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropIndex('accounts_operator_filter_idx');
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });
        Schema::table('customer_contacts', function (Blueprint $table): void {
            $table->dropIndex('customer_contacts_lookup_idx');
        });
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_operator_name_idx');
        });
    }

    /** @return list<string> */
    private function configuredSettlementLedgerIds(): array
    {
        $configured = config('banking.settlement_ledgers', []);
        if (! is_array($configured)) {
            return [];
        }

        $ids = [];
        foreach ($configured as $currencies) {
            if (! is_array($currencies)) {
                continue;
            }
            foreach ($currencies as $ledgerId) {
                if (is_string($ledgerId) && trim($ledgerId) !== '') {
                    $ids[] = $ledgerId;
                }
            }
        }

        return array_values(array_unique($ids));
    }
};
