<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Queries\ReportsPageData;
use App\Models\Game;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Game $game, ReportsPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Reports/Index', $pageData->for($game));
    }
}
