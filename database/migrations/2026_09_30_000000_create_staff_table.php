<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('employee_number', 20)->unique();
            $table->uuid('department_id');
            $table->string('job_title', 100);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('hired_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
