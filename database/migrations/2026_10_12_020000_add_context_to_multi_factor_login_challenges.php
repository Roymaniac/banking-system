<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Binds a challenge to the exact sensitive action being approved. */
    public function up(): void
    {
        Schema::table('multi_factor_login_challenges', function (Blueprint $table): void {
            $table->string('context_hash', 64)->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('multi_factor_login_challenges', function (Blueprint $table): void {
            $table->dropColumn('context_hash');
        });
    }
};
