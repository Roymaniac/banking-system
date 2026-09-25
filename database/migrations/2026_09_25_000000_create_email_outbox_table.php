<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores encrypted email requests until background delivery succeeds. */
    public function up(): void
    {
        Schema::create('email_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->longText('encrypted_payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('recorded_at')->index();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_outbox');
    }
};
