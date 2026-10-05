<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Links Laravel authentication records to the UUID-based Identity domain. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('identity_user_id')->nullable()->unique();
            $table->unsignedBigInteger('identity_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['identity_user_id']);
            $table->dropColumn(['identity_user_id', 'identity_version']);
        });
    }
};
