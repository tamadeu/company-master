<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Game\Services\GameAutomationClock;
use App\Jobs\ProcessGameDay;
use App\Models\Game;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;

function automaticGame(string $name, int $seed): Game
{
    $game = app(CreateGame::class)->execute(User::factory()->create(), $name, "Empresa {$name}", $seed);
    $game->update([
        'automation_enabled' => true,
        'next_processing_at' => app(GameAutomationClock::class)->nextProcessingAt(),
    ]);

    return $game->fresh();
}

test('a due game processes sales without an authenticated user', function () {
    CarbonImmutable::setTestNow('2026-09-21 12:00:00');
    $game = automaticGame('Automática', 5100);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }
    $game->update(['next_processing_at' => now()->subMinute()]);

    app()->call([new ProcessGameDay($game->id), 'handle']);

    $game->refresh();
    expect(auth()->check())->toBeFalse()
        ->and($game->current_date->toDateString())->toBe('2026-01-02')
        ->and($game->last_processed_at?->toDateTimeString())->toBe('2026-09-21 12:00:00')
        ->and($game->next_processing_at?->toDateTimeString())->toBe('2026-09-22 00:00:00')
        ->and(Sale::where('company_id', $game->company->id)->count())->toBe(1);
});

test('the scheduler dispatches only active due automated games', function () {
    Bus::fake();
    $due = automaticGame('Vencida', 5101);
    $future = automaticGame('Futura', 5102);
    $paused = automaticGame('Pausada', 5103);
    $due->update(['next_processing_at' => now()->subMinute()]);
    $future->update(['next_processing_at' => now()->addHour()]);
    $paused->update(['automation_enabled' => false, 'next_processing_at' => now()->subMinute()]);

    $this->artisan('games:dispatch-due')
        ->expectsOutput('1 partida(s) enviada(s) para processamento.')
        ->assertSuccessful();

    Bus::assertDispatched(ProcessGameDay::class, fn (ProcessGameDay $job) => $job->gameId === $due->id);
    Bus::assertNotDispatched(ProcessGameDay::class, fn (ProcessGameDay $job) => in_array($job->gameId, [$future->id, $paused->id], true));
});

test('the queued job ignores games that are not due or are paused', function () {
    $future = automaticGame('Ainda não', 5104);
    $paused = automaticGame('Sem automação', 5105);
    $future->update(['next_processing_at' => now()->addHour()]);
    $paused->update(['automation_enabled' => false, 'next_processing_at' => now()->subMinute()]);

    app()->call([new ProcessGameDay($future->id), 'handle']);
    app()->call([new ProcessGameDay($paused->id), 'handle']);

    expect($future->fresh()->current_date->toDateString())->toBe('2026-01-01')
        ->and($paused->fresh()->current_date->toDateString())->toBe('2026-01-01')
        ->and(Sale::count())->toBe(0);
});

test('manual processing also resets the automatic processing clock', function () {
    CarbonImmutable::setTestNow('2026-09-21 15:00:00');
    $game = automaticGame('Manual', 5106);

    app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect($game->fresh()->next_processing_at?->toDateTimeString())->toBe('2026-09-22 00:00:00');
});

test('the automation clock always targets the next server midnight', function () {
    CarbonImmutable::setTestNow('2026-09-21 23:59:30');

    expect(app(GameAutomationClock::class)->nextProcessingAt()->toDateTimeString())
        ->toBe('2026-09-22 00:00:00');
});
