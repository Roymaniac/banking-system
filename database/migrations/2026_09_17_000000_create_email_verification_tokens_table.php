<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store only hashed, expiring email-verification tokens.
     */
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->string('token', 64)->unique();
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
