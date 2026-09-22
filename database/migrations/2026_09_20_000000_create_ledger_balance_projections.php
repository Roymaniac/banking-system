<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates fast balances plus an idempotency record for every contribution. */
    public function up(): void
    {
        Schema::create('ledger_balances', function (Blueprint $table): void {
            $table->uuid('ledger_id')->primary();
            $table->char('currency', 3);
            $table->unsignedBigInteger('debit_minor_units')->default(0);
            $table->unsignedBigInteger('credit_minor_units')->default(0);
            $table->bigInteger('balance_minor_units')->default(0);
            $table->timestamp('updated_at');

            $table->foreign('ledger_id')->references('id')->on('ledgers')->restrictOnDelete();
        });

        Schema::create('ledger_balance_contributions', function (Blueprint $table): void {
            $table->uuid('entry_id');
            $table->uuid('ledger_id');
            $table->string('side', 10);
            $table->unsignedBigInteger('minor_units');
            $table->timestamp('projected_at');

            $table->primary(['entry_id', 'ledger_id']);
            $table->foreign('entry_id')->references('id')->on('ledger_entries')->restrictOnDelete();
            $table->foreign('ledger_id')->references('id')->on('ledgers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_balance_contributions');
        Schema::dropIfExists('ledger_balances');
    }
};
