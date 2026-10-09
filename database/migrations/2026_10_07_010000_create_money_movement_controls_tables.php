<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates the global switch and its immutable operational audit trail. */
    public function up(): void
    {
        Schema::create('money_movement_controls', function (Blueprint $table): void {
            $table->string('name', 50)->primary();
            $table->boolean('enabled');
            $table->string('reason', 255)->nullable();
            $table->string('source', 50);
            $table->timestamp('changed_at');
        });

        Schema::create('money_movement_control_events', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 20);
            $table->string('reason', 255);
            $table->string('source', 50);
            $table->timestamp('occurred_at');
        });

        DB::table('money_movement_controls')->insert([
            'name' => 'global',
            'enabled' => true,
            'reason' => null,
            'source' => 'migration',
            'changed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('money_movement_control_events');
        Schema::dropIfExists('money_movement_controls');
    }
};
