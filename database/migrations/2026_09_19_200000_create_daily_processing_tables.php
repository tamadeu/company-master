<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('game_date');
            $table->string('status')->default('completed');
            $table->bigInteger('revenue_cents')->default(0);
            $table->bigInteger('cogs_cents')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'game_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('unit_cost_cents');
            $table->bigInteger('revenue_cents');
            $table->bigInteger('cogs_cents');
            $table->timestamps();
            $table->unique(['sale_id', 'product_id']);
        });

        Schema::create('day_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('game_date');
            $table->string('status');
            $table->bigInteger('seed_used');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'game_date']);
        });

        Schema::create('daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->date('game_date');
            $table->bigInteger('sales_revenue_cents')->default(0);
            $table->unsignedInteger('units_sold')->default(0);
            $table->bigInteger('cogs_cents')->default(0);
            $table->bigInteger('expenses_cents')->default(0);
            $table->bigInteger('cash_change_cents')->default(0);
            $table->bigInteger('inventory_value_cents')->default(0);
            $table->json('summary');
            $table->timestamps();
            $table->unique(['game_id', 'game_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_values_non_negative CHECK (revenue_cents >= 0 AND cogs_cents >= 0)');
            DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_values_positive CHECK (quantity > 0 AND unit_price_cents > 0 AND unit_cost_cents >= 0 AND revenue_cents > 0 AND cogs_cents >= 0)');
            DB::statement("ALTER TABLE day_processes ADD CONSTRAINT day_processes_status_valid CHECK (status IN ('processing', 'completed', 'failed'))");
            DB::statement('ALTER TABLE daily_snapshots ADD CONSTRAINT daily_snapshots_values_non_negative CHECK (sales_revenue_cents >= 0 AND units_sold >= 0 AND cogs_cents >= 0 AND expenses_cents >= 0 AND inventory_value_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_snapshots');
        Schema::dropIfExists('day_processes');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
