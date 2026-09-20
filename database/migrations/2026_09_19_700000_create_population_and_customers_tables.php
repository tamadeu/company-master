<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('population_npcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->date('birth_date');
            $table->string('gender', 20);
            $table->string('city');
            $table->string('state', 2);
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('population_npc_id')->constrained()->restrictOnDelete();
            $table->date('acquired_on');
            $table->date('last_purchase_on');
            $table->unsignedInteger('purchase_count')->default(0);
            $table->bigInteger('lifetime_value_cents')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'population_npc_id']);
            $table->index(['company_id', 'last_purchase_on']);
        });

        Schema::create('customer_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('revenue_cents');
            $table->date('game_date');
            $table->timestamps();
            $table->unique(['sale_item_id', 'customer_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_valid CHECK (status IN ('active', 'inactive'))");
            DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_values_non_negative CHECK (purchase_count >= 0 AND lifetime_value_cents >= 0)');
            DB::statement('ALTER TABLE customer_purchases ADD CONSTRAINT customer_purchases_values_positive CHECK (quantity > 0 AND revenue_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_purchases');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('population_npcs');
    }
};
