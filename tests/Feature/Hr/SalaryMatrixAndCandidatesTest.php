<?php

use App\Domain\Game\Actions\CreateGame;
use App\Domain\Hr\Actions\HireEmployee;
use App\Domain\Hr\Services\CandidateRecommendationService;
use App\Domain\Hr\Services\SalaryMatrixService;
use App\Models\User;

test('salary matrix increases by role inside every department', function () {
    $matrix = app(SalaryMatrixService::class)->matrix();
    $roles = config('game.hr.roles');

    foreach ($matrix as $department => $salaries) {
        $ordered = collect($roles)->map(fn ($role) => $salaries[$role])->values();
        expect($ordered->all())->toBe($ordered->sort()->values()->all())
            ->and($ordered->unique()->count())->toBe(count($roles), "Salários repetidos em {$department}");
    }
});

test('the same role has different salaries across departments', function () {
    $matrix = app(SalaryMatrixService::class)->matrix();

    foreach (config('game.hr.roles') as $role) {
        expect(collect($matrix)->pluck($role)->unique()->count())->toBeGreaterThan(1);
    }
});

test('recommendations return three deterministic eligible population candidates', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Wizard', 'Mercado Aurora', 1100);
    $service = app(CandidateRecommendationService::class);

    $first = $service->recommend($game, 'Comercial', 'Diretor');
    $second = $service->recommend($game, 'Comercial', 'Diretor');

    expect($first)->toBe($second)
        ->and($first)->toHaveCount(3)
        ->and(collect($first)->pluck('populationNpcId')->unique()->count())->toBe(3)
        ->and(collect($first)->pluck('salaryCents')->unique()->all())->toBe([1_350_000]);
});

test('a hired npc is removed from future recommendations', function () {
    $game = app(CreateGame::class)->execute(User::factory()->create(), 'Wizard', 'Mercado Aurora', 1101);
    $service = app(CandidateRecommendationService::class);
    $candidate = $service->recommend($game, 'Logística', 'Analista')[0];

    app(HireEmployee::class)->execute($game, [
        'population_npc_id' => $candidate['populationNpcId'],
        'department' => 'Logística',
        'role' => 'Analista',
    ]);
    $nextRecommendations = $service->recommend($game, 'Logística', 'Analista');

    expect(collect($nextRecommendations)->pluck('populationNpcId'))->not->toContain($candidate['populationNpcId']);
});
