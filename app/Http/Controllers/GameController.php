<?php

namespace App\Http\Controllers;

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CompleteTutorial;
use App\Domain\Game\Actions\CreateGame;
use App\Domain\Game\Queries\GameDashboardData;
use App\Http\Requests\AdvanceDayRequest;
use App\Http\Requests\StoreGameRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function store(StoreGameRequest $request, CreateGame $createGame): RedirectResponse
    {
        $game = $createGame->execute(
            $request->user(),
            $request->validated('name'),
            $request->validated('company_name'),
        );

        return redirect()->route('games.show', $game);
    }

    public function show(Request $request, Game $game, GameDashboardData $dashboard): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Dashboard', $dashboard->for($game, $request->user()));
    }

    public function advance(AdvanceDayRequest $request, Game $game, AdvanceDay $advanceDay): RedirectResponse
    {
        Gate::authorize('view', $game);
        $summary = $advanceDay->execute($game, $request->validated('game_date'));

        return redirect()->route('games.show', $game)->with('daySummary', $summary);
    }

    public function completeTutorial(Game $game, CompleteTutorial $completeTutorial): RedirectResponse
    {
        Gate::authorize('view', $game);
        $completeTutorial->execute($game);

        return redirect()->route('games.show', $game);
    }
}
