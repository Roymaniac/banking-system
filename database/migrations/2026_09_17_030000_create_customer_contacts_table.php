<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stores each normalized contact channel owned by a customer. */
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type', 20);
            $table->string('value', 254);

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->unique(['customer_id', 'type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
