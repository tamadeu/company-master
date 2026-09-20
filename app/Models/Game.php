<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'name', 'status', 'seed', 'current_date', 'started_at', 'ended_at'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function dayProcesses(): HasMany
    {
        return $this->hasMany(DayProcess::class);
    }

    public function dailySnapshots(): HasMany
    {
        return $this->hasMany(DailySnapshot::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function populationNpcs(): HasMany
    {
        return $this->hasMany(PopulationNpc::class);
    }

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'current_date' => 'date',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
