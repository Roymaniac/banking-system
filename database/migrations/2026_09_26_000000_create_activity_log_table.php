<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates the append-only history of authenticated user actions. */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('action')->index();
            $table->string('http_method', 10);
            $table->unsignedSmallInteger('response_status');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata');
            $table->timestamp('occurred_on')->index();

            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
