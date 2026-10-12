<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores encrypted TOTP secrets and hashed single-use recovery codes. */
    public function up(): void
    {
        Schema::create('user_multi_factor_authentication', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->text('encrypted_secret');
            $table->text('recovery_code_hashes')->nullable();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('multi_factor_login_challenges', function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->uuid('user_id')->index();
            $table->string('device_name', 100);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_factor_login_challenges');
        Schema::dropIfExists('user_multi_factor_authentication');
    }
};
