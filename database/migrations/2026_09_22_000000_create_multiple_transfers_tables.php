<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores each batch once and preserves its ordered recipient payments. */
    public function up(): void
    {
        Schema::create('multiple_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('sender_account_id');
            $table->uuid('ledger_entry_id')->unique();
            $table->string('reference', 100)->unique();
            $table->unsignedBigInteger('total_minor_units');
            $table->char('currency', 3);
            $table->timestamp('completed_at');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreign('sender_account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('ledger_entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
        });

        Schema::create('multiple_transfer_items', function (Blueprint $table): void {
            $table->uuid('multiple_transfer_id');
            $table->unsignedInteger('position');
            $table->uuid('recipient_account_id');
            $table->unsignedBigInteger('minor_units');
            $table->char('currency', 3);
            $table->primary(['multiple_transfer_id', 'position']);
            $table->unique(['multiple_transfer_id', 'recipient_account_id']);
            $table->foreign('multiple_transfer_id')->references('id')->on('multiple_transfers')->cascadeOnDelete();
            $table->foreign('recipient_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiple_transfer_items');
        Schema::dropIfExists('multiple_transfers');
    }
};
