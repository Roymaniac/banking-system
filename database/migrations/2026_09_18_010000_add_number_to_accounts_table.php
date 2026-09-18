<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds the unique number customers use for transfers and statements. */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            // It is nullable while a newly created account awaits assignment.
            $table->char('number', 10)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }
};
