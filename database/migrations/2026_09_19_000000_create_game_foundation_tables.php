<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('seed');
            $table->date('current_date');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('cash_balance_cents')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->bigInteger('sale_price_cents');
            $table->bigInteger('reference_price_cents');
            $table->unsignedInteger('base_daily_demand');
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('profile');
            $table->unsignedSmallInteger('lead_time_days');
            $table->unsignedSmallInteger('payment_term_days');
            $table->unsignedSmallInteger('reliability_percent');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('cost_cents');
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->timestamps();
            $table->unique(['supplier_id', 'product_id']);
        });

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->bigInteger('average_cost_cents')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->bigInteger('amount_cents');
            $table->date('game_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('recurring')->default(false);
            $table->nullableMorphs('reference');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE companies ADD CONSTRAINT companies_cash_non_negative CHECK (cash_balance_cents >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_positive CHECK (sale_price_cents > 0 AND reference_price_cents > 0)');
            DB::statement('ALTER TABLE suppliers ADD CONSTRAINT suppliers_reliability_range CHECK (reliability_percent BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_cost_positive CHECK (cost_cents > 0)');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_non_negative CHECK (quantity >= 0 AND average_cost_cents >= 0)');
            DB::statement('ALTER TABLE financial_entries ADD CONSTRAINT financial_entries_amount_positive CHECK (amount_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('games');
    }
};
