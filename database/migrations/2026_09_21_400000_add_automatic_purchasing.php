<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_roles', function (Blueprint $table) {
            $table->unsignedInteger('purchasing_capacity_units')->default(0)->after('inventory_capacity_units');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $table->boolean('automatic')->default(false)->after('status');
        });

        $now = now();
        foreach (config('game.purchasing.capacity_by_role') as $role => $capacity) {
            DB::table('job_roles')->updateOrInsert(
                ['department' => 'Compras', 'name' => $role],
                [
                    'salary_cents' => config("game.hr.salary_matrix_cents.Compras.{$role}"),
                    'sales_capacity_units' => 0,
                    'inventory_capacity_units' => 0,
                    'purchasing_capacity_units' => $capacity,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('automatic');
        });

        Schema::table('job_roles', function (Blueprint $table) {
            $table->dropColumn('purchasing_capacity_units');
        });
    }
};
