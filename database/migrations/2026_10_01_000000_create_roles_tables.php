<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->string('label', 100);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('created_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
        });

        Schema::create('staff_role_assignments', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->uuid('staff_id');
            $table->timestamp('assigned_at');
            $table->primary(['role_id', 'staff_id']);
            $table->foreign('role_id')->references('id')->on('administration_roles')->restrictOnDelete();
            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_role_assignments');
        Schema::dropIfExists('administration_roles');
    }
};
