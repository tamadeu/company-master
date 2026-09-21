<?php

namespace App\Http\Controllers;

use App\Domain\Sales\Queries\CustomerOrderPageData;
use App\Models\CustomerOrder;
use App\Models\Game;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function show(Game $game, CustomerOrder $customerOrder, CustomerOrderPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        if ($customerOrder->company_id !== $game->company()->value('id')) {
            abort(404);
        }

        return Inertia::render('Sales/Show', $pageData->for($game, $customerOrder));
    }
}
