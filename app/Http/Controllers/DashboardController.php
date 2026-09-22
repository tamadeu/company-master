<?php

namespace App\Http\Controllers;

use App\Domain\Game\Services\GameDifficultyCatalog;
use App\Domain\Game\Services\OfficeLocationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, OfficeLocationCatalog $officeLocations, GameDifficultyCatalog $difficulties): Response|RedirectResponse
    {
        $game = $request->user()->games()->latest()->first();

        if ($game) {
            if ($request->boolean('new')) {
                return redirect()->route('games.show', ['game' => $game, 'new' => 1]);
            }

            return redirect()->route('games.show', $game);
        }

        return Inertia::render('Dashboard', [
            'games' => [],
            'game' => null,
            'company' => null,
            'officeLocations' => $officeLocations->options(),
            'difficulties' => $difficulties->options(),
            'metrics' => null,
            'products' => [],
            'suppliers' => [],
            'mission' => null,
            'tutorial' => ['completed' => true],
            'activeEvent' => null,
            'dailyHistory' => [],
            'upcomingDeliveries' => [],
            'salesByProduct' => [],
            'operationalAlerts' => [],
            'startNewGame' => true,
            'manualAdvanceEnabled' => app()->environment('local'),
        ]);
    }
}
