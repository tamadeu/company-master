<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('population_npc_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unique(['company_id', 'population_npc_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'population_npc_id']);
            $table->dropConstrainedForeignId('population_npc_id');
        });
    }
};
