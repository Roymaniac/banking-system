<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores proof that each idempotent deposit was posted successfully. */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('ledger_entry_id')->unique();
            $table->string('reference', 100)->unique();
            $table->unsignedBigInteger('minor_units');
            $table->char('currency', 3);
            $table->timestamp('completed_at');
            $table->unsignedBigInteger('version')->default(1);

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('ledger_entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
