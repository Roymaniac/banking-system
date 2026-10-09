<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keeps the incident reference and review evidence beside its approval request. */
    public function up(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            // Historical rows stay nullable because their missing evidence
            // cannot be reconstructed safely during deployment.
            $table->string('incident_reference', 50)->nullable()->index()->after('reason');
            $table->text('evidence_summary')->nullable()->after('incident_reference');
        });
    }

    public function down(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->dropIndex(['incident_reference']);
            $table->dropColumn(['incident_reference', 'evidence_summary']);
        });
    }
};
