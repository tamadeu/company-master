<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $game = $request->user()->games()->latest()->first();

        if ($game) {
            return redirect()->route('games.show', $game);
        }

        return Inertia::render('Dashboard', [
            'games' => [],
            'game' => null,
            'company' => null,
            'metrics' => null,
            'products' => [],
            'suppliers' => [],
            'mission' => null,
            'tutorial' => ['completed' => true],
            'activeEvent' => null,
            'dailyHistory' => [],
        ]);
    }
}
