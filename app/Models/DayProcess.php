<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'game_date', 'status', 'seed_used', 'error'])]
class DayProcess extends Model
{
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'seed_used' => 'integer',
        ];
    }
}
