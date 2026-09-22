<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('companies')->select('id')->orderBy('id')->chunkById(100, function ($companies) use ($now) {
            foreach ($companies as $company) {
                $supplierId = DB::table('suppliers')
                    ->where('company_id', $company->id)
                    ->where('name', 'Pronta Entrega')
                    ->value('id');

                if (! $supplierId) {
                    $supplierId = DB::table('suppliers')->insertGetId([
                        'company_id' => $company->id,
                        'name' => 'Pronta Entrega',
                        'profile' => 'Imediato',
                        'lead_time_days' => 0,
                        'payment_term_days' => 0,
                        'reliability_percent' => 100,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $products = DB::table('products')
                    ->join('product_templates', 'product_templates.sku', '=', 'products.sku')
                    ->where('products.company_id', $company->id)
                    ->get(['products.id', 'product_templates.base_cost_cents']);

                foreach ($products as $product) {
                    DB::table('supplier_products')->updateOrInsert(
                        ['supplier_id' => $supplierId, 'product_id' => $product->id],
                        [
                            'cost_cents' => intdiv(($product->base_cost_cents * 135) + 50, 100),
                            'minimum_quantity' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('suppliers')->where('name', 'Pronta Entrega')->delete();
    }
};
