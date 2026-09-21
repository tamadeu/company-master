<?php

use App\Domain\Customers\Services\PopulationNameCatalog;
use App\Models\PopulationNpc;

test('the command adds the requested population in batches with unique sequential codes', function () {
    expect(PopulationNpc::count())->toBe(100);

    $this->artisan('population:generate', ['quantity' => 501])
        ->expectsOutput('Gerando 501 pessoa(s)...')
        ->expectsOutput('501 pessoa(s) criada(s). População total: 601.')
        ->assertSuccessful();

    expect(PopulationNpc::count())->toBe(601)
        ->and(PopulationNpc::distinct('code')->count('code'))->toBe(601)
        ->and(PopulationNpc::distinct('name')->count('name'))->toBe(601)
        ->and(PopulationNpc::where('code', 'NPC-0101')->exists())->toBeTrue()
        ->and(PopulationNpc::where('code', 'NPC-0601')->exists())->toBeTrue();
});

test('the refresh command replaces repeated names without changing population ids or codes', function () {
    $people = PopulationNpc::query()->orderBy('id')->take(3)->get();
    PopulationNpc::whereKey($people->pluck('id'))->update(['name' => 'Nome Repetido']);

    $this->artisan('population:refresh-names', ['--chunk' => 100])->assertSuccessful();

    expect(PopulationNpc::count())->toBe(100)
        ->and(PopulationNpc::distinct('name')->count('name'))->toBe(100)
        ->and(PopulationNpc::whereKey($people[0]->id)->value('code'))->toBe($people[0]->code)
        ->and(PopulationNpc::where('name', 'Nome Repetido')->exists())->toBeFalse();
});

test('the refresh command rejects unsafe database batch sizes', function () {
    $this->artisan('population:refresh-names', ['--chunk' => 5_001])
        ->expectsOutput('O lote deve ser um número inteiro entre 100 e 5.000.')
        ->assertFailed();
});

test('successive command executions continue the existing sequence', function () {
    $this->artisan('population:generate', ['quantity' => 3])->assertSuccessful();
    $this->artisan('population:generate', ['quantity' => 2])->assertSuccessful();

    expect(PopulationNpc::count())->toBe(105)
        ->and(PopulationNpc::whereIn('code', [
            'NPC-0101',
            'NPC-0102',
            'NPC-0103',
            'NPC-0104',
            'NPC-0105',
        ])->count())->toBe(5);
});

test('the command rejects invalid population quantities', function (string $quantity) {
    $this->artisan('population:generate', ['quantity' => $quantity])
        ->expectsOutput('A quantidade deve ser um número inteiro entre 1 e 1.000.000.')
        ->assertFailed();

    expect(PopulationNpc::count())->toBe(100);
})->with(['zero' => '0', 'negative' => '-1', 'text' => 'muitas', 'above limit' => '1000001']);

test('the name catalog distributes consecutive people across distant combinations deterministically', function () {
    $catalog = app(PopulationNameCatalog::class);
    $names = collect(range(1, 500))->map(fn (int $index) => $catalog->personForIndex($index)['name']);
    $repeated = collect(range(1, 500))->map(fn (int $index) => $catalog->personForIndex($index)['name']);

    expect($names->unique()->count())->toBe(500)
        ->and($names->all())->toBe($repeated->all())
        ->and($names->all())->not->toBe($names->sort()->values()->all())
        ->and($names->map(fn (string $name) => explode(' ', $name)[0])->unique()->count())->toBeGreaterThan(100)
        ->and($names->map(fn (string $name) => collect(explode(' ', $name))->take(-2)->implode(' '))->unique()->count())->toBeGreaterThan(100);
});
