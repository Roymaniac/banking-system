<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ties each approval request to the exact safety-switch state it reviewed. */
    public function up(): void
    {
        Schema::table('money_movement_controls', function (Blueprint $table): void {
            $table->unsignedBigInteger('revision')->default(1)->after('changed_at');
        });

        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            // Existing requests remain nullable so they fail safely instead of
            // being assumed to belong to a suspension that predates this field.
            $table->unsignedBigInteger('control_revision')->nullable()->index()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->dropIndex(['control_revision']);
            $table->dropColumn('control_revision');
        });

        Schema::table('money_movement_controls', function (Blueprint $table): void {
            $table->dropColumn('revision');
        });
    }
};
