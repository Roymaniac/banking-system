<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores one open investigation per account and risk rule. */
    public function up(): void
    {
        Schema::create('transfer_risk_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('rule', 50);
            $table->string('status', 20)->index();
            $table->unsignedInteger('window_minutes');
            $table->unsignedInteger('maximum_transfers');
            $table->timestamp('detected_at');
            $table->uuid('claimed_by')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->char('open_key', 64)->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->index(['status', 'detected_at']);
            $table->index(['account_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_risk_cases');
    }
};
