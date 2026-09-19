<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores the current restriction reason and the time it began. */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('freeze_reason', 40)->nullable();
            $table->timestamp('frozen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['freeze_reason', 'frozen_at']);
        });
    }
};
