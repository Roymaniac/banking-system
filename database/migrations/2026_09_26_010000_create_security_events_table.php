<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates an investigation trail for authentication and authorization outcomes. */
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->string('severity', 20)->index();
            $table->string('subject_id')->nullable()->index();
            $table->string('subject_fingerprint', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details');
            $table->timestamp('occurred_on')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
