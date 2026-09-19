<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('department');
            $table->string('role');
            $table->bigInteger('monthly_salary_cents');
            $table->date('hired_on');
            $table->date('terminated_on')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_salary_positive CHECK (monthly_salary_cents > 0)');
            DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_valid CHECK (status IN ('active', 'terminated'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
