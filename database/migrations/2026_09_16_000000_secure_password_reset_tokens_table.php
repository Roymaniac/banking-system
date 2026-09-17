<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the expiry and uniqueness rules required by the secure reset flow.
     */
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('created_at');
            $table->unique('token');
        });
    }

    /**
     * Restore Laravel's original password-reset table structure.
     */
    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropUnique(['token']);
            $table->dropColumn('expires_at');
        });
    }
};
