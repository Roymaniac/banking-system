<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates one authoritative financial ledger for each account. */
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();
            $table->char('currency', 3);
            $table->timestamp('created_at');
            $table->unsignedBigInteger('version')->default(1);

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
