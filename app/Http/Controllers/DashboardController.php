<?php

namespace App\Http\Controllers;

use App\Domain\Game\Services\OfficeLocationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, OfficeLocationCatalog $officeLocations): Response|RedirectResponse
    {
        $game = $request->user()->games()->latest()->first();

        if ($game) {
            return redirect()->route('games.show', $game);
        }

        return Inertia::render('Dashboard', [
            'games' => [],
            'game' => null,
            'company' => null,
            'officeLocations' => $officeLocations->options(),
            'metrics' => null,
            'products' => [],
            'suppliers' => [],
            'mission' => null,
            'tutorial' => ['completed' => true],
            'activeEvent' => null,
            'dailyHistory' => [],
            'manualAdvanceEnabled' => app()->environment('local'),
        ]);
    }
}
