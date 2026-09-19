<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('ordered');
            $table->date('ordered_at_game_date');
            $table->date('expected_delivery_date');
            $table->date('received_at_game_date')->nullable();
            $table->bigInteger('total_cents');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expected_delivery_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_cost_cents');
            $table->bigInteger('total_cents');
            $table->timestamps();
            $table->unique(['purchase_order_id', 'product_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->bigInteger('unit_cost_cents');
            $table->bigInteger('total_cost_cents');
            $table->date('game_date');
            $table->morphs('reference');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'game_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_total_positive CHECK (total_cents > 0)');
            DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_valid CHECK (status IN ('ordered', 'received', 'cancelled'))");
            DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_values_positive CHECK (quantity > 0 AND unit_cost_cents > 0 AND total_cents > 0)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_non_zero CHECK (quantity <> 0)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_cost_non_negative CHECK (unit_cost_cents >= 0 AND total_cost_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
