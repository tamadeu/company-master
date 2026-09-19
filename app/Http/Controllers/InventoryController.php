<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Queries\InventoryPageData;
use App\Models\Game;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Game $game, InventoryPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Inventory/Index', $pageData->for($game));
    }
}
