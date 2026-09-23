<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores account limits separately from each banking day's consumed amount. */
    public function up(): void
    {
        Schema::create('daily_transaction_limits', function (Blueprint $table): void {
            $table->uuid('account_id')->primary();
            $table->char('currency', 3);
            $table->unsignedBigInteger('maximum_minor_units');
            $table->timestamp('configured_at');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::create('daily_transaction_limit_usages', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->date('usage_date');
            $table->char('currency', 3);
            $table->unsignedBigInteger('used_minor_units');
            $table->timestamp('updated_at');
            $table->primary(['account_id', 'usage_date']);
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_transaction_limit_usages');
        Schema::dropIfExists('daily_transaction_limits');
    }
};
