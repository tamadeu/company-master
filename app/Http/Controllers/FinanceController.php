<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Queries\FinancePageData;
use App\Models\Game;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function index(Game $game, FinancePageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Finance/Index', $pageData->for($game));
    }
}
