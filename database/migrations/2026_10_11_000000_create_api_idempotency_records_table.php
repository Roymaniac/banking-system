<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores successful money responses so a safe retry cannot move money twice. */
    public function up(): void
    {
        Schema::create('api_idempotency_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('actor_id');
            $table->string('route', 150);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('state', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('content_type', 100)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();

            $table->unique(['actor_id', 'route', 'key_hash'], 'api_idempotency_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_records');
    }
};
