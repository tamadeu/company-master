<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Purchasing\Actions\CancelPurchaseOrder;
use App\Domain\Purchasing\Actions\CreatePurchaseOrder;
use App\Domain\Purchasing\Queries\PurchasesPageData;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Game;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Game $game, PurchasesPageData $pageData): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('Purchases/Index', $pageData->for($game));
    }

    public function store(StorePurchaseOrderRequest $request, Game $game, CreatePurchaseOrder $createOrder): RedirectResponse
    {
        Gate::authorize('view', $game);
        $supplier = Supplier::findOrFail($request->integer('supplier_id'));

        $order = $createOrder->execute($game, $supplier, $request->validated('items'));

        return redirect()->route('games.purchases.index', $game)->with(
            'success',
            $order->status === 'received' ? 'Pedido criado e recebido no estoque.' : 'Pedido criado com sucesso.',
        );
    }

    public function receive(Game $game, PurchaseOrder $purchaseOrder, ReceivePurchaseOrder $receiveOrder): RedirectResponse
    {
        Gate::authorize('view', $game);
        $receiveOrder->execute($game, $purchaseOrder);

        return redirect()->route('games.purchases.index', $game)->with('success', 'Pedido recebido no estoque.');
    }

    public function cancel(Game $game, PurchaseOrder $purchaseOrder, CancelPurchaseOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('view', $game);
        $cancelOrder->execute($game, $purchaseOrder);

        return redirect()->route('games.purchases.index', $game)->with('success', 'Pedido cancelado e vagas de estoque liberadas.');
    }
}
