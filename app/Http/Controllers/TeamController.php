<?php

namespace App\Http\Controllers;

use App\Domain\Hr\Actions\HireEmployee;
use App\Domain\Hr\Actions\TerminateEmployee;
use App\Domain\Hr\Queries\TeamPageData;
use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Employee;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Game $game, TeamPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Team/Index', $pageData->for($game));
    }

    public function store(StoreEmployeeRequest $request, Game $game, HireEmployee $hireEmployee): RedirectResponse
    {
        Gate::authorize('view', $game);
        $hireEmployee->execute($game, $request->validated());

        return redirect()->route('games.team.index', $game)->with('success', 'Funcionário contratado.');
    }

    public function terminate(Game $game, Employee $employee, TerminateEmployee $terminateEmployee): RedirectResponse
    {
        Gate::authorize('view', $game);
        $terminateEmployee->execute($game, $employee);

        return redirect()->route('games.team.index', $game)->with('success', 'Funcionário desligado.');
    }
}
