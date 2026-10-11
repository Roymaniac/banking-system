<?php

declare(strict_types=1);

use Account\Domain\Account\ValueObject\AccountId;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Ledger\Domain\Ledger\ValueObject\LedgerId;
use Shared\Domain\Identifier\Uuid;
use Transaction\Application\Control\Exception\InvalidMoneyMovementResume;
use Transaction\Application\Control\MoneyMovementResumeApproval;
use Transaction\Application\Control\MoneyMovementResumeNotifier;
use Transaction\Application\Control\MoneyMovementResumeRequest;
use Transaction\Application\Withdrawal\MakeWithdrawal;
use Transaction\Application\Withdrawal\MakeWithdrawalCommand;
use Transaction\Domain\Withdrawal\Exception\InsufficientFunds;

uses(DatabaseMigrations::class);

final class PostgreSqlConcurrencyNotifier implements MoneyMovementResumeNotifier
{
    public function pending(MoneyMovementResumeRequest $request): int
    {
        return 1;
    }

    public function expiringSoon(MoneyMovementResumeRequest $request): int
    {
        return 1;
    }

    public function outcome(MoneyMovementResumeRequest $request): int
    {
        // Keep the successful approval transaction open so its competitor
        // demonstrably waits for PostgreSQL to release the control-row lock.
        usleep(400000);

        return 1;
    }
}

/** @return array{account_id: string, customer_ledger_id: string, settlement_ledger_id: string} */
function seedPostgreSqlWithdrawalRace(): array
{
    $customerId = Uuid::generate()->value();
    $accountId = Uuid::generate()->value();
    $settlementAccountId = Uuid::generate()->value();
    $customerLedgerId = Uuid::generate()->value();
    $settlementLedgerId = Uuid::generate()->value();
    $now = now();

    DB::table('customers')->insert([
        'id' => $customerId,
        'user_id' => Uuid::generate()->value(),
        'first_name' => 'Concurrency',
        'middle_name' => null,
        'last_name' => 'Test',
        'date_of_birth' => '1990-01-01',
        'registered_at' => $now,
        'version' => 1,
    ]);
    DB::table('accounts')->insert([
        [
            'id' => $accountId,
            'customer_id' => $customerId,
            'type' => 'savings',
            'currency' => 'NGN',
            'created_at' => $now,
            'version' => 3,
            'number' => '1234567890',
            'status' => 'active',
        ],
        [
            'id' => $settlementAccountId,
            'customer_id' => $customerId,
            'type' => 'current',
            'currency' => 'NGN',
            'created_at' => $now,
            'version' => 3,
            'number' => '2234567890',
            'status' => 'active',
        ],
    ]);
    DB::table('ledgers')->insert([
        [
            'id' => $customerLedgerId,
            'account_id' => $accountId,
            'currency' => 'NGN',
            'created_at' => $now,
            'version' => 1,
        ],
        [
            'id' => $settlementLedgerId,
            'account_id' => $settlementAccountId,
            'currency' => 'NGN',
            'created_at' => $now,
            'version' => 1,
        ],
    ]);
    DB::table('ledger_balances')->insert([
        'ledger_id' => $customerLedgerId,
        'currency' => 'NGN',
        'debit_minor_units' => 0,
        'credit_minor_units' => 10000,
        'balance_minor_units' => 10000,
        'updated_at' => $now,
    ]);

    // Keep the winning transaction open briefly after it has acquired the
    // financial locks, giving the competing process time to reach the same row.
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION test_pause_withdrawal_insert() RETURNS trigger AS $$
        BEGIN
            PERFORM pg_sleep(0.4);
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER test_pause_withdrawal_insert
        BEFORE INSERT ON withdrawals
        FOR EACH ROW EXECUTE FUNCTION test_pause_withdrawal_insert();
        SQL);

    return [
        'account_id' => $accountId,
        'customer_ledger_id' => $customerLedgerId,
        'settlement_ledger_id' => $settlementLedgerId,
    ];
}

/** @return array{result: string, duration_ms: int} */
function runPostgreSqlApprovalChild(
    string $requestId,
    string $approverId,
    string $readyFile,
    string $gateFile,
): array {
    file_put_contents($readyFile, 'ready');
    $deadline = microtime(true) + 10;

    while (! file_exists($gateFile)) {
        if (microtime(true) >= $deadline) {
            return ['result' => 'error: start gate timed out', 'duration_ms' => 0];
        }

        usleep(10000);
    }

    DB::disconnect();
    DB::reconnect();
    $startedAt = microtime(true);

    try {
        app(MoneyMovementResumeApproval::class)->approve(
            new Uuid($requestId),
            new Uuid($approverId),
        );
        $result = 'approved';
    } catch (InvalidMoneyMovementResume) {
        $result = 'conflict';
    } catch (Throwable $exception) {
        $result = 'error: '.$exception::class.': '.$exception->getMessage();
    } finally {
        DB::disconnect();
    }

    return [
        'result' => $result,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ];
}

/** @return array{result: string, duration_ms: int} */
function runPostgreSqlWithdrawalChild(
    string $accountId,
    string $settlementLedgerId,
    string $reference,
    string $readyFile,
    string $gateFile,
): array {
    file_put_contents($readyFile, 'ready');
    $deadline = microtime(true) + 10;

    while (! file_exists($gateFile)) {
        if (microtime(true) >= $deadline) {
            return ['result' => 'error: start gate timed out', 'duration_ms' => 0];
        }

        usleep(10000);
    }

    DB::disconnect();
    DB::reconnect();
    $startedAt = microtime(true);

    try {
        app(MakeWithdrawal::class)->handle(new MakeWithdrawalCommand(
            new AccountId($accountId),
            new LedgerId($settlementLedgerId),
            8000,
            $reference,
            new DateTimeImmutable,
        ));
        $result = 'completed';
    } catch (InsufficientFunds) {
        $result = 'insufficient';
    } catch (Throwable $exception) {
        $result = 'error: '.$exception::class.': '.$exception->getMessage();
    } finally {
        DB::disconnect();
    }

    return [
        'result' => $result,
        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ];
}

it('serializes competing withdrawals so the same funds cannot be spent twice', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This test requires PostgreSQL row locks.');
    }
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This test requires the PCNTL extension.');
    }

    $records = seedPostgreSqlWithdrawalRace();
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'banking-concurrency-'.Uuid::generate()->value();
    mkdir($directory, 0700, true);
    $gateFile = $directory.DIRECTORY_SEPARATOR.'start';
    $children = [];

    // Do not let forked workers inherit the parent's active PDO connection.
    DB::disconnect();

    foreach (['RACE-WITHDRAWAL-001', 'RACE-WITHDRAWAL-002'] as $index => $reference) {
        $resultFile = $directory.DIRECTORY_SEPARATOR."result-{$index}.json";
        $readyFile = $directory.DIRECTORY_SEPARATOR."ready-{$index}";
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to start a concurrent withdrawal process.');
        }
        if ($pid === 0) {
            $result = runPostgreSqlWithdrawalChild(
                $records['account_id'],
                $records['settlement_ledger_id'],
                $reference,
                $readyFile,
                $gateFile,
            );
            file_put_contents($resultFile, json_encode($result, JSON_THROW_ON_ERROR));
            exit(str_starts_with($result['result'], 'error:') ? 1 : 0);
        }

        $children[] = ['pid' => $pid, 'ready' => $readyFile, 'result' => $resultFile];
    }

    $readyDeadline = microtime(true) + 10;
    while (! file_exists($children[0]['ready']) || ! file_exists($children[1]['ready'])) {
        if (microtime(true) >= $readyDeadline) {
            throw new RuntimeException('Concurrent withdrawal workers did not become ready.');
        }

        usleep(10000);
    }
    touch($gateFile);

    foreach ($children as $child) {
        pcntl_waitpid($child['pid'], $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }

    DB::reconnect();
    $results = array_map(
        static fn (array $child): array => json_decode(
            (string) file_get_contents($child['result']),
            true,
            flags: JSON_THROW_ON_ERROR,
        ),
        $children,
    );
    $outcomes = array_column($results, 'result');
    sort($outcomes);

    expect($outcomes)->toBe(['completed', 'insufficient'])
        ->and(min(array_column($results, 'duration_ms')))->toBeGreaterThanOrEqual(350)
        ->and(DB::table('withdrawals')->count())->toBe(1)
        ->and(DB::table('ledger_balances')
            ->where('ledger_id', $records['customer_ledger_id'])
            ->value('balance_minor_units'))->toBe(2000)
        ->and(DB::table('daily_transaction_limit_usages')
            ->where('account_id', $records['account_id'])
            ->value('used_minor_units'))->toBe(8000);

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
});

it('allows only one operator to approve a resume request', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('This test requires PostgreSQL row locks.');
    }
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This test requires the PCNTL extension.');
    }

    app()->instance(MoneyMovementResumeNotifier::class, new PostgreSqlConcurrencyNotifier);
    $requestId = Uuid::generate()->value();
    $requesterId = Uuid::generate()->value();
    DB::table('money_movement_controls')->where('name', 'global')->update([
        'enabled' => false,
        'reason' => 'A concurrency test temporarily suspended financial writes.',
        'source' => 'postgresql_test',
        'changed_at' => now(),
        'revision' => 1,
    ]);
    DB::table('money_movement_resume_requests')->insert([
        'id' => $requestId,
        'requested_by' => $requesterId,
        'reason' => 'Concurrency verification confirms recovery is ready for approval.',
        'incident_reference' => 'INC-CONCURRENCY-001',
        'evidence_summary' => 'PostgreSQL reconciliation and settlement checks completed successfully.',
        'control_revision' => 1,
        'status' => 'pending',
        'requested_at' => now(),
        'expires_at' => now()->addMinutes(30),
    ]);

    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'banking-approval-'.Uuid::generate()->value();
    mkdir($directory, 0700, true);
    $gateFile = $directory.DIRECTORY_SEPARATOR.'start';
    $children = [];
    DB::disconnect();

    foreach ([Uuid::generate()->value(), Uuid::generate()->value()] as $index => $approverId) {
        $resultFile = $directory.DIRECTORY_SEPARATOR."result-{$index}.json";
        $readyFile = $directory.DIRECTORY_SEPARATOR."ready-{$index}";
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to start a concurrent approval process.');
        }
        if ($pid === 0) {
            $result = runPostgreSqlApprovalChild(
                $requestId,
                $approverId,
                $readyFile,
                $gateFile,
            );
            file_put_contents($resultFile, json_encode($result, JSON_THROW_ON_ERROR));
            exit(str_starts_with($result['result'], 'error:') ? 1 : 0);
        }

        $children[] = ['pid' => $pid, 'ready' => $readyFile, 'result' => $resultFile];
    }

    $readyDeadline = microtime(true) + 10;
    while (! file_exists($children[0]['ready']) || ! file_exists($children[1]['ready'])) {
        if (microtime(true) >= $readyDeadline) {
            throw new RuntimeException('Concurrent approval workers did not become ready.');
        }

        usleep(10000);
    }
    touch($gateFile);

    foreach ($children as $child) {
        pcntl_waitpid($child['pid'], $status);
        expect(pcntl_wexitstatus($status))->toBe(0);
    }

    DB::reconnect();
    $results = array_map(
        static fn (array $child): array => json_decode(
            (string) file_get_contents($child['result']),
            true,
            flags: JSON_THROW_ON_ERROR,
        ),
        $children,
    );
    $outcomes = array_column($results, 'result');
    sort($outcomes);

    expect($outcomes)->toBe(['approved', 'conflict'])
        ->and(min(array_column($results, 'duration_ms')))->toBeGreaterThanOrEqual(350)
        ->and(DB::table('money_movement_resume_requests')->where('id', $requestId)->value('status'))
        ->toBe('approved')
        ->and((bool) DB::table('money_movement_controls')->where('name', 'global')->value('enabled'))
        ->toBeTrue()
        ->and(DB::table('money_movement_control_events')->where('action', 'resumed')->count())
        ->toBe(1);

    foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
});
