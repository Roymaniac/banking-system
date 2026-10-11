<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_due_diligence_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->unique();
            $table->string('status', 20)->index();
            $table->string('document_type', 30);
            $table->char('issuing_country', 2);
            $table->text('document_number_encrypted');
            $table->char('document_fingerprint', 64)->unique();
            $table->string('document_last_four', 4);
            $table->string('risk_level', 20)->nullable()->index();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->unsignedBigInteger('version')->default(1);

            // Compliance evidence must not disappear because a customer record
            // is deleted accidentally; retention is handled deliberately.
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });

        Schema::create('customer_due_diligence_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->string('action', 20);
            $table->uuid('actor_id');
            $table->string('reason', 500)->nullable();
            $table->timestamp('occurred_at');

            $table->foreign('case_id')->references('id')->on('customer_due_diligence_cases')->cascadeOnDelete();
            $table->index(['case_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_due_diligence_events');
        Schema::dropIfExists('customer_due_diligence_cases');
    }
};
