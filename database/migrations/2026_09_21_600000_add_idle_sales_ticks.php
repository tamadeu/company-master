<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'game_date']);
            $table->string('tick_key')->nullable()->after('game_date');
            $table->unsignedInteger('commercial_capacity_units')->default(0);
            $table->unsignedInteger('unmet_demand_units')->default(0);
            $table->unsignedInteger('new_customers')->default(0);
            $table->unsignedInteger('customer_purchases')->default(0);
            $table->json('stockout_product_ids')->nullable();
            $table->unique(['company_id', 'tick_key']);
            $table->index(['company_id', 'game_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'tick_key']);
            $table->dropIndex(['company_id', 'game_date']);
            $table->dropColumn([
                'tick_key',
                'commercial_capacity_units',
                'unmet_demand_units',
                'new_customers',
                'customer_purchases',
                'stockout_product_ids',
            ]);
            $table->unique(['company_id', 'game_date']);
        });
    }
};
