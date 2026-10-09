<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Identifies the authenticated operator when a state change came from the API. */
    public function up(): void
    {
        Schema::table('money_movement_control_events', function (Blueprint $table): void {
            $table->uuid('actor_user_id')->nullable()->index()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('money_movement_control_events', function (Blueprint $table): void {
            $table->dropColumn('actor_user_id');
        });
    }
};
