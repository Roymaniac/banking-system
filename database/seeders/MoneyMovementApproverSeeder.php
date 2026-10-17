<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** Creates the independent operator used to approve demo resumption requests. */
final class MoneyMovementApproverSeeder extends Seeder
{
    private const PASSWORD = 'DemoPassword123!';

    private const USER_ID = '10000000-0000-4000-8000-000000000005';

    private const DEPARTMENT_ID = '80000000-0000-4000-8000-000000000001';

    private const STAFF_ID = '81000000-0000-4000-8000-000000000002';

    private const ROLE_ID = '82000000-0000-4000-8000-000000000002';

    /**
     * The approver can inspect the control and its history before deciding.
     * Management is intentionally excluded so they cannot approve their own change.
     *
     * @var array<string, array{id: string, label: string}>
     */
    private const PERMISSIONS = [
        'money_movement.approve' => [
            'id' => '83000000-0000-4000-8000-000000000013',
            'label' => 'Approve money movement resumption',
        ],
        'money_movement.audit' => [
            'id' => '83000000-0000-4000-8000-000000000014',
            'label' => 'View money movement history',
        ],
        'operations.view' => [
            'id' => '83000000-0000-4000-8000-000000000019',
            'label' => 'View operational health',
        ],
    ];

    public function run(): void
    {
        if (
            ! in_array(app()->environment(), ['local', 'testing'], true)
            && ! config('demo.seeding_enabled')
        ) {
            throw new RuntimeException('The demo money movement approver may only be seeded locally or during tests.');
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('users')->updateOrInsert(
                ['email' => 'approver@example.com'],
                [
                    'identity_user_id' => self::USER_ID,
                    'identity_version' => 1,
                    'name' => 'Demo Money Movement Approver',
                    // Reviewer notifications are sent only to verified users.
                    'email_verified_at' => $now,
                    'password' => Hash::make(self::PASSWORD),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

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
                    'user_id' => self::USER_ID,
                    'employee_number' => 'DEMO-APPROVER-001',
                    'department_id' => self::DEPARTMENT_ID,
                    'job_title' => 'Money Movement Approval Officer',
                    'status' => 'active',
                    'hired_at' => $now,
                    'deactivated_at' => null,
                    'version' => 1,
                ],
            );

            DB::table('administration_roles')->updateOrInsert(
                ['id' => self::ROLE_ID],
                [
                    'name' => 'demo_money_movement_approver',
                    'label' => 'Demo Money Movement Approver',
                    'status' => 'active',
                    'created_at' => $now,
                    'deactivated_at' => null,
                    'version' => 1,
                ],
            );

            DB::table('staff_role_assignments')->updateOrInsert(
                ['role_id' => self::ROLE_ID, 'staff_id' => self::STAFF_ID],
                ['assigned_at' => $now],
            );

            foreach (self::PERMISSIONS as $name => $permission) {
                DB::table('administration_permissions')->updateOrInsert(
                    ['id' => $permission['id']],
                    [
                        'name' => $name,
                        'label' => $permission['label'],
                        'created_at' => $now,
                        'version' => 1,
                    ],
                );

                DB::table('role_permission_assignments')->updateOrInsert(
                    ['role_id' => self::ROLE_ID, 'permission_id' => $permission['id']],
                    ['granted_at' => $now],
                );
            }
        });

        $this->command?->info('Demo resumption approver: approver@example.com / '.self::PASSWORD);
    }
}
