<?php

namespace App\Http\Controllers;

use App\Domain\Customers\Queries\CustomerDetailData;
use App\Domain\Customers\Queries\CustomersPageData;
use App\Models\Customer;
use App\Models\Game;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Game $game, CustomersPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Customers/Index', $pageData->for($game));
    }

    public function show(Game $game, Customer $customer, CustomerDetailData $pageData): Response
    {
        Gate::authorize('view', $game);

        if ($customer->company_id !== $game->company()->value('id')) {
            abort(404);
        }

        return Inertia::render('Customers/Show', $pageData->for($game, $customer));
    }
}
