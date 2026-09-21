<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\InboxMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a processed sale creates a structured notification for the game owner', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Notificações', 'Mercado Notificado', 6100);
    foreach ($game->company->inventoryBalances as $balance) {
        $balance->update(['quantity' => 100, 'average_cost_cents' => 1_000]);
    }

    $summary = app(AdvanceDay::class)->execute($game, '2026-01-01');

    $notification = InboxMessage::where('recipient_user_id', $user->id)->where('category', 'sale')->firstOrFail();
    expect($notification->metadata)->toMatchArray([
        'game_id' => $game->id,
        'game_name' => 'Notificações',
        'company_name' => 'Mercado Notificado',
        'game_date' => '2026-01-01',
        'revenue_cents' => $summary['sales_revenue_cents'],
        'units_sold' => $summary['units_sold'],
        'new_customers' => $summary['new_customers'],
    ])->and($notification->action_url)->toBe("/games/{$game->id}/products")
        ->and($notification->read_at)->toBeNull();
});

test('a day without completed sales does not create a sales notification', function () {
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Sem vendas', 'Mercado Vazio', 6101);

    app(AdvanceDay::class)->execute($game, '2026-01-01');

    expect(InboxMessage::where('recipient_user_id', $user->id)->where('category', 'sale')->exists())->toBeFalse();
});

test('the notifications area is isolated by user and marks sales notifications as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $metadata = [
        'game_id' => 1,
        'game_name' => 'Partida',
        'company_name' => 'Empresa',
        'game_date' => '2026-01-01',
        'revenue_cents' => 125_000,
        'units_sold' => 10,
        'new_customers' => 4,
    ];
    $mine = InboxMessage::create([
        'recipient_user_id' => $user->id,
        'category' => 'sale',
        'subject' => '10 unidades vendidas',
        'body' => 'Vendas processadas.',
        'metadata' => $metadata,
    ]);
    InboxMessage::create([
        'recipient_user_id' => $other->id,
        'category' => 'sale',
        'subject' => 'Mensagem de outro usuário',
        'body' => 'Restrita.',
        'metadata' => $metadata,
    ]);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.id', $mine->id)
            ->where('notifications.data.0.wasUnread', true)
            ->where('notifications.data.0.metadata.revenue_cents', 125_000)
            ->where('auth.unreadSalesNotificationCount', 0));

    expect($mine->fresh()->read_at)->not->toBeNull();
});
