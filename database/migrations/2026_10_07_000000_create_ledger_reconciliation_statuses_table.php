<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores the latest safe reconciliation result without financial details. */
    public function up(): void
    {
        Schema::create('ledger_reconciliation_statuses', function (Blueprint $table): void {
            $table->string('name', 50)->primary();
            $table->string('status', 20);
            $table->unsignedBigInteger('unbalanced_posted_entries');
            $table->unsignedBigInteger('contribution_mismatches');
            $table->unsignedBigInteger('balance_mismatches');
            $table->timestamp('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reconciliation_statuses');
    }
};
