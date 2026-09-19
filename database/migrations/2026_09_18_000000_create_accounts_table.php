<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Creates the core account records owned by customers. */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type', 20);
            $table->char('currency', 3);
            $table->timestamp('created_at');
            $table->unsignedBigInteger('version')->default(1);

            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
