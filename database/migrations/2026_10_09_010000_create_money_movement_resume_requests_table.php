<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores short-lived requests that require approval from another operator. */
    public function up(): void
    {
        Schema::create('money_movement_resume_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requested_by');
            $table->string('reason', 255);
            $table->string('status', 20);
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->index(['status', 'expires_at']);
            $table->index('requested_by');
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movement_resume_requests');
    }
};
