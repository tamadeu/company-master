<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('job_roles', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('name');
            $table->bigInteger('salary_cents');
            $table->unsignedInteger('sales_capacity_units')->default(0);
            $table->unsignedInteger('inventory_capacity_units')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['department', 'name']);
        });

        Schema::create('product_templates', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->bigInteger('reference_price_cents');
            $table->unsignedInteger('base_daily_demand');
            $table->bigInteger('base_cost_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $salesCapacities = config('game.sales.commercial_capacity_by_role');
        $inventoryCapacities = config('game.inventory.capacity_by_logistics_role');

        foreach (config('game.hr.salary_matrix_cents') as $department => $roles) {
            foreach ($roles as $name => $salaryCents) {
                DB::table('job_roles')->insert([
                    'department' => $department,
                    'name' => $name,
                    'salary_cents' => $salaryCents,
                    'sales_capacity_units' => $department === 'Comercial' ? ($salesCapacities[$name] ?? 0) : 0,
                    'inventory_capacity_units' => $department === 'Logística' ? ($inventoryCapacities[$name] ?? 0) : 0,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (config('game.products') as $product) {
            DB::table('product_templates')->insert([
                ...$product,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_templates');
        Schema::dropIfExists('job_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
