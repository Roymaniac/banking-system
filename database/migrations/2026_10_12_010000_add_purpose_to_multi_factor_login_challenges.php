<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Distinguishes ordinary login from privileged step-up challenges. */
    public function up(): void
    {
        Schema::table('multi_factor_login_challenges', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('login')->index()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('multi_factor_login_challenges', function (Blueprint $table): void {
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
