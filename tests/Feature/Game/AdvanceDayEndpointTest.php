<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\DailySnapshot;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner advances the expected game date and receives a summary', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Avanço', 'Mercado Aurora', 500);

    $response = $this->actingAs($user)->post(route('games.advance-day', $game), [
        'game_date' => '2026-01-01',
    ]);

    $response
        ->assertRedirect(route('games.show', $game))
        ->assertSessionHas('daySummary.processed_date', '2026-01-01');
    expect($game->fresh()->current_date->toDateString())->toBe('2026-01-02')
        ->and(DailySnapshot::count())->toBe(1);

    $this->actingAs($user)
        ->get(route('games.show', $game))
        ->assertInertia(fn (Assert $page) => $page->where('game.dayNumber', 2));
});

test('a repeated expected date does not advance a second day', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Idempotência', 'Mercado Aurora', 501);

    $this->actingAs($user)->post(route('games.advance-day', $game), ['game_date' => '2026-01-01']);
    $this->actingAs($user)->post(route('games.advance-day', $game), ['game_date' => '2026-01-01']);

    expect($game->fresh()->current_date->toDateString())->toBe('2026-01-02')
        ->and(DailySnapshot::count())->toBe(1);
});

test('another user cannot advance the game', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Empresa privada', 502);

    $this->actingAs($intruder)
        ->post(route('games.advance-day', $game), ['game_date' => '2026-01-01'])
        ->assertForbidden();
});

test('manual day advancement is forbidden in production', function () {
    $this->app->instance('env', 'production');
    $this->withoutMiddleware(PreventRequestForgery::class);
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Produção', 'Empresa', 503);

    $this->actingAs($user)
        ->post(route('games.advance-day', $game), ['game_date' => '2026-01-01'])
        ->assertForbidden();
});
