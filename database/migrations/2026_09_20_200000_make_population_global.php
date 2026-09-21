<?php

use App\Domain\Customers\Services\PopulationGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('population_npcs', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'code']);
        });

        $duplicateCodes = DB::table('population_npcs')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $people = DB::table('population_npcs')
                ->where('code', $code)
                ->get(['id', 'game_id']);

            foreach ($people as $person) {
                DB::table('population_npcs')
                    ->where('id', $person->id)
                    ->update(['code' => "G{$person->game_id}-{$code}"]);
            }
        }

        Schema::table('population_npcs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_id');
            $table->unique('code');
        });

        app(PopulationGenerator::class)->generate();
    }

    public function down(): void
    {
        Schema::table('population_npcs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->foreignId('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['game_id', 'code']);
        });
    }
};
