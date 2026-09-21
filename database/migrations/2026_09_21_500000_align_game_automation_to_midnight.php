<?php

use App\Domain\Game\Services\GameAutomationClock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')
            ->where('status', 'active')
            ->update([
                'automation_enabled' => app()->environment('production') && config('game.automation.enabled'),
                'next_processing_at' => app()->environment('production') && config('game.automation.enabled')
                    ? app(GameAutomationClock::class)->nextProcessingAt()
                    : null,
            ]);
    }

    public function down(): void
    {
        DB::table('games')
            ->where('status', 'active')
            ->update(['next_processing_at' => null]);
    }
};
