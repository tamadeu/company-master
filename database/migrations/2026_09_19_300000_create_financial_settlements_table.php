<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_entries', function (Blueprint $table) {
            $table->date('settled_game_date')->nullable()->after('paid_at');
        });

        Schema::create('financial_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->bigInteger('amount_cents');
            $table->date('game_date');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'game_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE financial_settlements ADD CONSTRAINT financial_settlements_type_valid CHECK (type IN ('inflow', 'outflow'))");
            DB::statement('ALTER TABLE financial_settlements ADD CONSTRAINT financial_settlements_amount_positive CHECK (amount_cents > 0)');
        }

        foreach (DB::table('financial_entries')->whereNotNull('paid_at')->orderBy('id')->get() as $entry) {
            DB::table('financial_entries')->where('id', $entry->id)->update([
                'settled_game_date' => $entry->game_date,
            ]);
            DB::table('financial_settlements')->insert([
                'company_id' => $entry->company_id,
                'financial_entry_id' => $entry->id,
                'type' => $entry->type,
                'amount_cents' => $entry->amount_cents,
                'game_date' => $entry->game_date,
                'created_at' => $entry->paid_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settlements');

        Schema::table('financial_entries', function (Blueprint $table) {
            $table->dropColumn('settled_game_date');
        });
    }
};
