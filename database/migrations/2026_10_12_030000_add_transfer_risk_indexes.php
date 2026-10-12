<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keeps rolling risk checks fast as each account builds transaction history. */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->index(
                ['sender_account_id', 'completed_at'],
                'transfers_sender_completed_at_index',
            );
        });

        Schema::table('multiple_transfers', function (Blueprint $table): void {
            $table->index(
                ['sender_account_id', 'completed_at'],
                'multiple_transfers_sender_completed_at_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropIndex('transfers_sender_completed_at_index');
        });

        Schema::table('multiple_transfers', function (Blueprint $table): void {
            $table->dropIndex('multiple_transfers_sender_completed_at_index');
        });
    }
};
