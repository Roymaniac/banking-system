<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Records why a pending request was rejected or cancelled. */
    public function up(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->uuid('closed_by')->nullable()->index()->after('approved_at');
            $table->string('closure_reason', 255)->nullable()->after('closed_by');
            $table->timestamp('closed_at')->nullable()->after('closure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('money_movement_resume_requests', function (Blueprint $table): void {
            $table->dropIndex(['closed_by']);
            $table->dropColumn(['closed_by', 'closure_reason', 'closed_at']);
        });
    }
};
