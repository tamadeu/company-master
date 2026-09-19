<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'type', 'title', 'description', 'payload', 'triggered_on', 'starts_on', 'ends_on', 'status'])]
class GameEvent extends Model
{
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'triggered_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
