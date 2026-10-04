<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Preserves manual retry history even when the current attempt count restarts. */
    public function up(): void
    {
        Schema::table('email_outbox', function (Blueprint $table): void {
            $table->unsignedInteger('retry_cycles')->default(0);
            $table->timestamp('requeued_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_outbox', function (Blueprint $table): void {
            $table->dropIndex(['requeued_at']);
            $table->dropColumn(['retry_cycles', 'requeued_at']);
        });
    }
};
