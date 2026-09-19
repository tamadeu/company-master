<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description');
            $table->json('payload');
            $table->date('triggered_on');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['game_id', 'status', 'starts_on', 'ends_on']);
            $table->unique(['game_id', 'triggered_on']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE game_events ADD CONSTRAINT game_events_status_valid CHECK (status IN ('active', 'expired'))");
            DB::statement('ALTER TABLE game_events ADD CONSTRAINT game_events_dates_valid CHECK (ends_on >= starts_on)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
