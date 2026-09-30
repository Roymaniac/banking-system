<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administration_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('label', 150);
            $table->timestamp('created_at');
            $table->unsignedBigInteger('version')->default(1);
        });

        Schema::create('role_permission_assignments', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->uuid('permission_id');
            $table->timestamp('granted_at');
            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('administration_roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('administration_permissions')->restrictOnDelete();
            $table->index('permission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_assignments');
        Schema::dropIfExists('administration_permissions');
    }
};
