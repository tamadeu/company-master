<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('automation_enabled')->default(true)->after('status');
            $table->timestamp('next_processing_at')->nullable()->index()->after('ended_at');
            $table->timestamp('last_processed_at')->nullable()->after('next_processing_at');
        });

        DB::table('games')
            ->where('status', 'active')
            ->update(['next_processing_at' => now()->addMinutes(config('game.automation.interval_minutes'))]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['next_processing_at']);
            $table->dropColumn(['automation_enabled', 'next_processing_at', 'last_processed_at']);
        });
    }
};
