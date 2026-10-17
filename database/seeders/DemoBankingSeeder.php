<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Creates repeatable customer and administrator records for the local web apps. */
final class DemoBankingSeeder extends Seeder
{
    private const PASSWORD = 'DemoPassword123!';

    private const CUSTOMER_USER_ID = '10000000-0000-4000-8000-000000000001';

    private const RECIPIENT_USER_ID = '10000000-0000-4000-8000-000000000002';

    private const ADMIN_USER_ID = '10000000-0000-4000-8000-000000000003';

    private const SYSTEM_USER_ID = '10000000-0000-4000-8000-000000000004';

    private const CUSTOMER_ID = '20000000-0000-4000-8000-000000000001';

    private const RECIPIENT_ID = '20000000-0000-4000-8000-000000000002';

    private const SYSTEM_CUSTOMER_ID = '20000000-0000-4000-8000-000000000003';

    private const CUSTOMER_ACCOUNT_ID = '30000000-0000-4000-8000-000000000001';

    private const RECIPIENT_ACCOUNT_ID = '30000000-0000-4000-8000-000000000002';

    private const NGN_DEPOSIT_SETTLEMENT_ACCOUNT_ID = '30000000-0000-4000-8000-000000000003';

    private const NGN_WITHDRAWAL_SETTLEMENT_ACCOUNT_ID = '30000000-0000-4000-8000-000000000004';

    private const CUSTOMER_LEDGER_ID = '40000000-0000-4000-8000-000000000001';

    private const RECIPIENT_LEDGER_ID = '40000000-0000-4000-8000-000000000002';

    private const NGN_DEPOSIT_SETTLEMENT_LEDGER_ID = '40000000-0000-4000-8000-000000000003';

    private const NGN_WITHDRAWAL_SETTLEMENT_LEDGER_ID = '40000000-0000-4000-8000-000000000004';

    private const DEPARTMENT_ID = '80000000-0000-4000-8000-000000000001';

    private const STAFF_ID = '81000000-0000-4000-8000-000000000001';

    private const ROLE_ID = '82000000-0000-4000-8000-000000000001';

    /** @var array<string, string> */
    private const PERMISSIONS = [
        'accounts.close' => 'Close accounts',
        'accounts.freeze' => 'Freeze accounts',
        'accounts.provision' => 'Provision accounts',
        'activity.view' => 'View user activity',
        'administration.view' => 'View administration',
        'audit.view' => 'View domain audit records',
        'compliance.review' => 'Review due diligence',
        'compliance.view' => 'View due diligence',
        'customer_reports.view' => 'View customer reports',
        'departments.manage' => 'Manage departments',
        'documentation.view' => 'View API documentation',
        'ledger_reports.view' => 'View ledger reports',
        'money_movement.approve' => 'Approve money movement resumption',
        'money_movement.audit' => 'View money movement history',
        'money_movement.break_glass' => 'Use emergency money movement recovery',
        'money_movement.manage' => 'Manage money movement controls',
        'notifications.retry' => 'Retry failed notifications',
        'notifications.view' => 'View notification delivery',
        'operations.view' => 'View operational health',
        'permissions.manage' => 'Manage permissions',
        'risk_cases.review' => 'Review transfer risk cases',
        'risk_cases.view' => 'View transfer risk cases',
        'roles.manage' => 'Manage roles',
        'security.view' => 'View security events',
        'staff.manage' => 'Manage staff',
        'transaction_reports.view' => 'View transaction reports',
        'transactions.deposit' => 'Create deposits',
        'transactions.reverse' => 'Reverse transactions',
        'transactions.withdraw' => 'Create withdrawals',
    ];

    public function run(): void
    {
        if (
            ! in_array(app()->environment(), ['local', 'testing'], true)
            && ! config('demo.seeding_enabled')
        ) {
            throw new RuntimeException('Demo banking data may only be seeded locally or during tests.');
        }

        DB::transaction(function (): void {
            $now = now();
            $password = Hash::make(self::PASSWORD);

            $this->seedUsers($password, $now);
            $this->seedCustomers($now);
            $this->seedAccountsAndLedgers($now);
            $this->seedApprovedDueDiligence($now);
            $this->seedAdministrator($now);
            $this->seedStartingBalances($now);
        });

        // A second operator is required because requesters may never approve
        // their own money movement resumption request.
        $this->call(MoneyMovementApproverSeeder::class);

        $this->command?->info('Demo customer: customer@example.com / '.self::PASSWORD);
        $this->command?->info('Demo recipient: recipient@example.com / '.self::PASSWORD);
        $this->command?->info('Demo administrator: admin@example.com / '.self::PASSWORD);
    }

    private function seedUsers(string $password, mixed $now): void
    {
        $users = [
            [self::CUSTOMER_USER_ID, 'Demo Customer', 'customer@example.com', $password],
            [self::RECIPIENT_USER_ID, 'Demo Recipient', 'recipient@example.com', $password],
            [self::ADMIN_USER_ID, 'Demo Administrator', 'admin@example.com', $password],
            // This identity owns the bank's internal settlement ledgers and
            // cannot be signed in with any published demo credential.
            [self::SYSTEM_USER_ID, 'Demo Settlement Identity', 'settlement@example.invalid', Hash::make(Str::random(64))],
        ];

        foreach ($users as [$identityId, $name, $email, $userPassword]) {
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'identity_user_id' => $identityId,
                    'identity_version' => 1,
                    'name' => $name,
                    'email_verified_at' => $now,
                    'password' => $userPassword,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function seedCustomers(mixed $now): void
    {
        $customers = [
            [self::CUSTOMER_ID, self::CUSTOMER_USER_ID, 'Ada', null, 'Okafor', '1992-04-16'],
            [self::RECIPIENT_ID, self::RECIPIENT_USER_ID, 'Tunde', null, 'Adeyemi', '1989-09-22'],
            [self::SYSTEM_CUSTOMER_ID, self::SYSTEM_USER_ID, 'Demo', null, 'Settlement', '1990-01-01'],
        ];

        foreach ($customers as [$id, $userId, $firstName, $middleName, $lastName, $dateOfBirth]) {
            DB::table('customers')->updateOrInsert(
                ['id' => $id],
                [
                    'user_id' => $userId,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'date_of_birth' => $dateOfBirth,
                    'registered_at' => $now,
                    'version' => 1,
                ],
            );
        }
    }

    private function seedAccountsAndLedgers(mixed $now): void
    {
        $accounts = [
            [self::CUSTOMER_ACCOUNT_ID, self::CUSTOMER_ID, '1000000001', false],
            [self::RECIPIENT_ACCOUNT_ID, self::RECIPIENT_ID, '1000000002', false],
            [self::NGN_DEPOSIT_SETTLEMENT_ACCOUNT_ID, self::SYSTEM_CUSTOMER_ID, '9000000001', true],
            [self::NGN_WITHDRAWAL_SETTLEMENT_ACCOUNT_ID, self::SYSTEM_CUSTOMER_ID, '9000000002', true],
        ];
        $ledgers = [
            [self::CUSTOMER_LEDGER_ID, self::CUSTOMER_ACCOUNT_ID],
            [self::RECIPIENT_LEDGER_ID, self::RECIPIENT_ACCOUNT_ID],
            [self::NGN_DEPOSIT_SETTLEMENT_LEDGER_ID, self::NGN_DEPOSIT_SETTLEMENT_ACCOUNT_ID],
            [self::NGN_WITHDRAWAL_SETTLEMENT_LEDGER_ID, self::NGN_WITHDRAWAL_SETTLEMENT_ACCOUNT_ID],
        ];

        foreach ($accounts as [$id, $customerId, $number, $isInternal]) {
            DB::table('accounts')->updateOrInsert(
                ['id' => $id],
                [
                    'customer_id' => $customerId,
                    'type' => 'savings',
                    'currency' => 'NGN',
                    'number' => $number,
                    'status' => 'active',
                    'is_internal' => $isInternal,
                    'created_at' => $now,
                    'version' => 2,
                ],
            );
        }

        foreach ($ledgers as [$id, $accountId]) {
            DB::table('ledgers')->updateOrInsert(
                ['id' => $id],
                [
                    'account_id' => $accountId,
                    'currency' => 'NGN',
                    'created_at' => $now,
                    'version' => 1,
                ],
            );
        }

        foreach ([self::CUSTOMER_ACCOUNT_ID, self::RECIPIENT_ACCOUNT_ID] as $accountId) {
            DB::table('daily_transaction_limits')->updateOrInsert(
                ['account_id' => $accountId],
                [
                    'currency' => 'NGN',
                    'maximum_minor_units' => 50000000,
                    'customer_maximum_minor_units' => 20000000,
                    'configured_at' => $now,
                    'version' => 1,
                ],
            );
        }
    }

    private function seedApprovedDueDiligence(mixed $now): void
    {
        foreach ([
            [self::CUSTOMER_ID, '90000000-0000-4000-8000-000000000001', '0001'],
            [self::RECIPIENT_ID, '90000000-0000-4000-8000-000000000002', '0002'],
        ] as [$customerId, $caseId, $lastFour]) {
            DB::table('customer_due_diligence_cases')->updateOrInsert(
                ['customer_id' => $customerId],
                [
                    'id' => $caseId,
                    'status' => 'approved',
                    'document_type' => 'national_id',
                    'issuing_country' => 'NG',
                    'document_number_encrypted' => Crypt::encryptString('DEMO-DOCUMENT-'.$lastFour),
                    'document_fingerprint' => hash('sha256', 'demo-document-'.$lastFour),
                    'document_last_four' => $lastFour,
                    'risk_level' => 'low',
                    'submitted_at' => $now,
                    'reviewed_at' => $now,
                    'reviewed_by' => self::ADMIN_USER_ID,
                    'rejection_reason' => null,
                    'version' => 2,
                ],
            );
        }
    }

    private function seedAdministrator(mixed $now): void
    {
        DB::table('departments')->updateOrInsert(
            ['id' => self::DEPARTMENT_ID],
            [
                'code' => 'DEMO_OPS',
                'name' => 'Demo Banking Operations',
                'status' => 'active',
                'created_at' => $now,
                'version' => 1,
            ],
        );
        DB::table('staff')->updateOrInsert(
            ['id' => self::STAFF_ID],
            [
                'user_id' => self::ADMIN_USER_ID,
                'employee_number' => 'DEMO-ADMIN-001',
                'department_id' => self::DEPARTMENT_ID,
                'job_title' => 'Demo Platform Administrator',
                'status' => 'active',
                'hired_at' => $now,
                'version' => 1,
            ],
        );
        DB::table('administration_roles')->updateOrInsert(
            ['id' => self::ROLE_ID],
            [
                'name' => 'demo-super-administrator',
                'label' => 'Demo Super Administrator',
                'status' => 'active',
                'created_at' => $now,
                'version' => 1,
            ],
        );
        DB::table('staff_role_assignments')->updateOrInsert(
            ['role_id' => self::ROLE_ID, 'staff_id' => self::STAFF_ID],
            ['assigned_at' => $now],
        );

        $position = 0;

        foreach (self::PERMISSIONS as $name => $label) {
            $position++;
            $permissionId = DB::table('administration_permissions')
                ->where('name', $name)
                ->value('id') ?? sprintf('83000000-0000-4000-8000-%012d', $position);

            DB::table('administration_permissions')->updateOrInsert(
                ['id' => $permissionId],
                [
                    'name' => $name,
                    'label' => $label,
                    'created_at' => $now,
                    'version' => 1,
                ],
            );
            DB::table('role_permission_assignments')->updateOrInsert(
                ['role_id' => self::ROLE_ID, 'permission_id' => $permissionId],
                ['granted_at' => $now],
            );
        }
    }

    private function seedStartingBalances(mixed $now): void
    {
        $deposits = [
            [
                'entry_id' => '50000000-0000-4000-8000-000000000001',
                'deposit_id' => '70000000-0000-4000-8000-000000000001',
                'credit_posting_id' => '60000000-0000-4000-8000-000000000001',
                'debit_posting_id' => '60000000-0000-4000-8000-000000000002',
                'account_id' => self::CUSTOMER_ACCOUNT_ID,
                'ledger_id' => self::CUSTOMER_LEDGER_ID,
                'reference' => 'DEMO-FUNDING-001',
                'minor_units' => 25000000,
            ],
            [
                'entry_id' => '50000000-0000-4000-8000-000000000002',
                'deposit_id' => '70000000-0000-4000-8000-000000000002',
                'credit_posting_id' => '60000000-0000-4000-8000-000000000003',
                'debit_posting_id' => '60000000-0000-4000-8000-000000000004',
                'account_id' => self::RECIPIENT_ACCOUNT_ID,
                'ledger_id' => self::RECIPIENT_LEDGER_ID,
                'reference' => 'DEMO-FUNDING-002',
                'minor_units' => 5000000,
            ],
        ];

        foreach ($deposits as $deposit) {
            if (DB::table('ledger_entries')->where('id', $deposit['entry_id'])->exists()) {
                continue;
            }

            DB::table('ledger_entries')->insert([
                'id' => $deposit['entry_id'],
                'ledger_id' => $deposit['ledger_id'],
                'reference' => $deposit['reference'],
                'description' => 'Local demo opening balance',
                'occurred_at' => $now,
                'recorded_at' => $now,
                'status' => 'posted',
                'version' => 4,
            ]);
            DB::table('ledger_postings')->insert([
                [
                    'id' => $deposit['debit_posting_id'],
                    'entry_id' => $deposit['entry_id'],
                    'ledger_id' => self::NGN_DEPOSIT_SETTLEMENT_LEDGER_ID,
                    'side' => 'debit',
                    'minor_units' => $deposit['minor_units'],
                    'currency' => 'NGN',
                ],
                [
                    'id' => $deposit['credit_posting_id'],
                    'entry_id' => $deposit['entry_id'],
                    'ledger_id' => $deposit['ledger_id'],
                    'side' => 'credit',
                    'minor_units' => $deposit['minor_units'],
                    'currency' => 'NGN',
                ],
            ]);
            DB::table('ledger_balance_contributions')->insert([
                [
                    'entry_id' => $deposit['entry_id'],
                    'ledger_id' => self::NGN_DEPOSIT_SETTLEMENT_LEDGER_ID,
                    'side' => 'debit',
                    'minor_units' => $deposit['minor_units'],
                    'projected_at' => $now,
                ],
                [
                    'entry_id' => $deposit['entry_id'],
                    'ledger_id' => $deposit['ledger_id'],
                    'side' => 'credit',
                    'minor_units' => $deposit['minor_units'],
                    'projected_at' => $now,
                ],
            ]);
            DB::table('deposits')->insert([
                'id' => $deposit['deposit_id'],
                'account_id' => $deposit['account_id'],
                'ledger_entry_id' => $deposit['entry_id'],
                'reference' => $deposit['reference'],
                'minor_units' => $deposit['minor_units'],
                'currency' => 'NGN',
                'completed_at' => $now,
                'version' => 1,
            ]);
        }

        // Insert initial projections only once. Later seeder runs must never
        // overwrite balances changed by real local testing activity.
        DB::table('ledger_balances')->insertOrIgnore([
            [
                'ledger_id' => self::NGN_DEPOSIT_SETTLEMENT_LEDGER_ID,
                'currency' => 'NGN',
                'debit_minor_units' => 30000000,
                'credit_minor_units' => 0,
                'balance_minor_units' => -30000000,
                'updated_at' => $now,
            ],
            [
                'ledger_id' => self::CUSTOMER_LEDGER_ID,
                'currency' => 'NGN',
                'debit_minor_units' => 0,
                'credit_minor_units' => 25000000,
                'balance_minor_units' => 25000000,
                'updated_at' => $now,
            ],
            [
                'ledger_id' => self::RECIPIENT_LEDGER_ID,
                'currency' => 'NGN',
                'debit_minor_units' => 0,
                'credit_minor_units' => 5000000,
                'balance_minor_units' => 5000000,
                'updated_at' => $now,
            ],
        ]);
    }
}
