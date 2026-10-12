<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds durable reminder and escalation checkpoints to risk cases. */
    public function up(): void
    {
        Schema::table('transfer_risk_cases', function (Blueprint $table): void {
            $table->timestamp('due_at')->nullable()->after('detected_at')->index();
            $table->timestamp('reminded_at')->nullable()->after('due_at');
            $table->timestamp('escalated_at')->nullable()->after('reminded_at');
        });

        // Existing open cases become immediately visible as overdue instead of
        // silently escaping the newly introduced service-level deadline.
        DB::table('transfer_risk_cases')
            ->whereNull('due_at')
            ->update(['due_at' => DB::raw('detected_at')]);
    }

    public function down(): void
    {
        Schema::table('transfer_risk_cases', function (Blueprint $table): void {
            $table->dropColumn(['due_at', 'reminded_at', 'escalated_at']);
        });
    }
};
