<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores immutable debit and credit lines belonging to ledger entries. */
    public function up(): void
    {
        Schema::create('ledger_postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('entry_id');
            $table->uuid('ledger_id');
            $table->string('side', 10);
            $table->unsignedBigInteger('minor_units');
            $table->char('currency', 3);

            $table->foreign('entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
            $table->foreign('ledger_id')->references('id')->on('ledgers')->restrictOnDelete();
            $table->unique(['entry_id', 'ledger_id']);
            $table->index(['ledger_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_postings');
    }
};
