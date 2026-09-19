<?php

use App\Domain\Game\Actions\CreateGame;
use App\Models\User;

test('the owner completes the tutorial persistently', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Tutorial', 'Mercado Aurora', 900);

    $this->actingAs($user)
        ->post(route('games.tutorial.complete', $game))
        ->assertRedirect(route('games.show', $game));

    expect($game->company->fresh()->settings['tutorial_completed'])->toBeTrue();
});

test('another user cannot complete the tutorial', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $game = app(CreateGame::class)->execute($owner, 'Privada', 'Privada', 901);

    $this->actingAs($intruder)
        ->post(route('games.tutorial.complete', $game))
        ->assertForbidden();
});
