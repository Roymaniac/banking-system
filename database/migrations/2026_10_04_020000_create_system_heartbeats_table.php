<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores small freshness markers written by critical background processes. */
    public function up(): void
    {
        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_heartbeats');
    }
};
