<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->bigInteger('revenue_cents');
            $table->timestamps();
            $table->index(['employee_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sale_attributions ADD CONSTRAINT sale_attributions_values_positive CHECK (quantity > 0 AND revenue_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_attributions');
    }
};
