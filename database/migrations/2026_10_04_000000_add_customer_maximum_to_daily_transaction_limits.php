<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Keeps a customer's lower preference separate from the bank ceiling. */
    public function up(): void
    {
        Schema::table('daily_transaction_limits', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_maximum_minor_units')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('daily_transaction_limits', function (Blueprint $table): void {
            $table->dropColumn('customer_maximum_minor_units');
        });
    }
};
