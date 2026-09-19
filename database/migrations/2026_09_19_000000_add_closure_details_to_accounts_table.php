<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores the permanent account closure reason and timestamp. */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('closure_reason', 40)->nullable();
            $table->timestamp('closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn(['closure_reason', 'closed_at']);
        });
    }
};
