<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores draft and posted financial entry headers for each ledger. */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('ledger_id');
            $table->string('reference', 100);
            $table->string('description', 255);
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('version')->default(1);

            $table->foreign('ledger_id')->references('id')->on('ledgers')->restrictOnDelete();
            $table->unique(['ledger_id', 'reference']);
            $table->index(['ledger_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
