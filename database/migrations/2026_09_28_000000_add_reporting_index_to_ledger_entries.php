<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Speeds up bank-wide reports that filter posted entries by date. */
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->index(['status', 'occurred_at'], 'ledger_entries_reporting_index');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table): void {
            $table->dropIndex('ledger_entries_reporting_index');
        });
    }
};
