<?php

namespace App\Http\Controllers;

use App\Domain\Sales\Queries\ProductsPageData;
use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Game;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Game $game, ProductsPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Products/Index', $pageData->for($game));
    }

    public function update(UpdateProductPriceRequest $request, Game $game, Product $product): RedirectResponse
    {
        Gate::authorize('view', $game);

        if ($product->company_id !== $game->company()->value('id')) {
            abort(404);
        }

        $product->update(['sale_price_cents' => $request->integer('sale_price_cents')]);

        return redirect()->route('games.products.index', $game)->with('success', 'Preço atualizado.');
    }
}
