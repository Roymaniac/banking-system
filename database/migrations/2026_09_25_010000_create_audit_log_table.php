<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates the append-only history of domain changes. */
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table): void {
            // The event ID also prevents the same event from being recorded twice.
            $table->uuid('event_id')->primary();
            $table->string('event_name')->index();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->unsignedBigInteger('aggregate_version');
            $table->uuid('correlation_id')->nullable()->index();
            $table->json('payload');
            $table->timestamp('occurred_on')->index();
            $table->timestamp('recorded_at')->index();

            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
