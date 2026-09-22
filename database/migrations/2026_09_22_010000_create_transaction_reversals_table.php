<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keeps an immutable audit link between the original and compensating entries. */
    public function up(): void
    {
        Schema::create('transaction_reversals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('original_ledger_entry_id')->unique();
            $table->uuid('reversal_ledger_entry_id')->unique();
            $table->string('reference', 100)->unique();
            $table->string('reason', 255);
            $table->timestamp('completed_at');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreign('original_ledger_entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
            $table->foreign('reversal_ledger_entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_reversals');
    }
};
