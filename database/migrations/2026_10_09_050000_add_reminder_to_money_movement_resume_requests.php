<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ensures each pending approval receives at most one expiry reminder. */
    public function up(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable()->index()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->dropIndex(['reminded_at']);
            $table->dropColumn('reminded_at');
        });
    }
};
