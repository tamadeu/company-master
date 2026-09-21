<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('game_date');
            $table->string('status')->default('completed');
            $table->unsignedInteger('total_quantity')->default(0);
            $table->bigInteger('revenue_cents')->default(0);
            $table->timestamps();
            $table->unique(['sale_id', 'customer_id']);
            $table->index(['company_id', 'game_date']);
        });

        Schema::table('customer_purchases', function (Blueprint $table) {
            $table->foreignId('customer_order_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $groups = DB::table('customer_purchases as purchases')
            ->join('sale_items as items', 'items.id', '=', 'purchases.sale_item_id')
            ->join('sales', 'sales.id', '=', 'items.sale_id')
            ->selectRaw('items.sale_id, sales.company_id, purchases.customer_id, purchases.game_date, SUM(purchases.quantity) AS total_quantity, SUM(purchases.revenue_cents) AS revenue_cents, MIN(purchases.created_at) AS created_at, MAX(purchases.updated_at) AS updated_at')
            ->groupBy('items.sale_id', 'sales.company_id', 'purchases.customer_id', 'purchases.game_date')
            ->orderBy('items.sale_id')
            ->cursor();

        foreach ($groups as $group) {
            $orderId = DB::table('customer_orders')->insertGetId([
                'sale_id' => $group->sale_id,
                'company_id' => $group->company_id,
                'customer_id' => $group->customer_id,
                'game_date' => $group->game_date,
                'status' => 'completed',
                'total_quantity' => $group->total_quantity,
                'revenue_cents' => $group->revenue_cents,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ]);

            DB::table('customer_purchases')
                ->where('customer_id', $group->customer_id)
                ->whereIn('sale_item_id', DB::table('sale_items')->where('sale_id', $group->sale_id)->select('id'))
                ->update(['customer_order_id' => $orderId]);
        }

        DB::table('customers')->update(['purchase_count' => 0]);
        foreach (DB::table('customer_orders')->selectRaw('customer_id, COUNT(*) AS total')->groupBy('customer_id')->cursor() as $total) {
            DB::table('customers')->where('id', $total->customer_id)->update(['purchase_count' => $total->total]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE customer_orders ADD CONSTRAINT customer_orders_values_non_negative CHECK (total_quantity >= 0 AND revenue_cents >= 0)');
        }
    }

    public function down(): void
    {
        Schema::table('customer_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_order_id');
        });
        Schema::dropIfExists('customer_orders');
    }
};
