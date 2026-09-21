<?php

use App\Domain\Game\Actions\AdvanceDay;
use App\Domain\Game\Actions\CreateGame;
use App\Models\InboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('an administrator sends a message to one user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $player = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.messages.store'), [
        'audience' => 'user',
        'recipient_user_id' => $player->id,
        'subject' => 'Manutenção programada',
        'body' => 'O sistema ficará indisponível por alguns minutos.',
        'action_url' => '/dashboard',
    ])->assertSessionHasNoErrors();

    $message = InboxMessage::firstOrFail();
    expect($message->recipient_user_id)->toBe($player->id)
        ->and($message->sender_user_id)->toBe($admin->id)
        ->and($message->category)->toBe('admin')
        ->and($message->subject)->toBe('Manutenção programada');
});

test('broadcast creates an independent inbox message for every player', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $players = User::factory()->count(3)->create();

    $this->actingAs($admin)->post(route('admin.messages.store'), [
        'audience' => 'all_players',
        'subject' => 'Novidade',
        'body' => 'Uma nova funcionalidade está disponível.',
        'action_url' => null,
    ])->assertSessionHasNoErrors();

    expect(InboxMessage::count())->toBe(3)
        ->and(InboxMessage::pluck('recipient_user_id')->sort()->values()->all())
        ->toBe($players->pluck('id')->sort()->values()->all())
        ->and(InboxMessage::where('recipient_user_id', $admin->id)->exists())->toBeFalse();
});

test('users can only read and remove their own messages', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $message = InboxMessage::create([
        'recipient_user_id' => $owner->id,
        'category' => 'system',
        'subject' => 'Aviso privado',
        'body' => 'Conteúdo restrito.',
    ]);

    $this->actingAs($intruder)->get(route('inbox.show', $message))->assertForbidden();
    $this->actingAs($intruder)->delete(route('inbox.destroy', $message))->assertForbidden();

    $this->actingAs($owner)->get(route('inbox.show', $message))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox/Index')
            ->where('selectedMessage.id', $message->id)
            ->where('auth.unreadInboxCount', 0));

    expect($message->fresh()->read_at)->not->toBeNull();

    $this->actingAs($owner)->delete(route('inbox.destroy', $message))->assertRedirect(route('inbox.index'));
    expect($message->fresh())->toBeNull();
});

test('the inbox lists only the authenticated users messages and shares unread count', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    InboxMessage::create(['recipient_user_id' => $user->id, 'category' => 'alert', 'subject' => 'Minha mensagem', 'body' => 'Texto']);
    InboxMessage::create(['recipient_user_id' => $other->id, 'category' => 'alert', 'subject' => 'Mensagem alheia', 'body' => 'Texto']);

    $this->actingAs($user)->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox/Index')
            ->has('messages.data', 1)
            ->where('messages.data.0.subject', 'Minha mensagem')
            ->where('auth.unreadInboxCount', 1));
});

test('a triggered game event creates a system inbox alert', function () {
    Config::set('game.events.daily_chance_basis_points', 10_000);
    Config::set('game.events.definitions', [
        'heavy_rain' => ['weight' => 1, 'duration_days' => 2, 'demand_factor_basis_points' => 8_500],
    ]);
    $user = User::factory()->create();
    $game = app(CreateGame::class)->execute($user, 'Alertas', 'Empresa Alerta', 4100);

    app(AdvanceDay::class)->execute($game, '2026-01-01');

    $message = InboxMessage::where('recipient_user_id', $user->id)->where('category', 'event')->firstOrFail();
    expect($message->subject)->toBe('Chuva intensa')
        ->and($message->action_url)->toBe("/games/{$game->id}")
        ->and($message->read_at)->toBeNull();
});

test('non administrators cannot send inbox announcements', function () {
    $player = User::factory()->create();

    $this->actingAs($player)->post(route('admin.messages.store'), [
        'audience' => 'all_players',
        'subject' => 'Tentativa',
        'body' => 'Sem acesso.',
    ])->assertForbidden();

    expect(InboxMessage::count())->toBe(0);
});

test('users mark all of their own inbox messages as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    InboxMessage::create(['recipient_user_id' => $user->id, 'category' => 'alert', 'subject' => 'Primeira', 'body' => 'Texto']);
    InboxMessage::create(['recipient_user_id' => $user->id, 'category' => 'alert', 'subject' => 'Segunda', 'body' => 'Texto']);
    $foreign = InboxMessage::create(['recipient_user_id' => $other->id, 'category' => 'alert', 'subject' => 'Alheia', 'body' => 'Texto']);

    $this->actingAs($user)->patch(route('inbox.read-all'))->assertSessionHasNoErrors();

    expect($user->inboxMessages()->whereNull('read_at')->count())->toBe(0)
        ->and($foreign->fresh()->read_at)->toBeNull();
});

test('bulk deletion removes only selected messages owned by the user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $selected = InboxMessage::create(['recipient_user_id' => $user->id, 'category' => 'alert', 'subject' => 'Excluir', 'body' => 'Texto']);
    $kept = InboxMessage::create(['recipient_user_id' => $user->id, 'category' => 'alert', 'subject' => 'Manter', 'body' => 'Texto']);
    $foreign = InboxMessage::create(['recipient_user_id' => $other->id, 'category' => 'alert', 'subject' => 'Alheia', 'body' => 'Texto']);

    $this->actingAs($user)->delete(route('inbox.destroy-bulk'), [
        'ids' => [$selected->id, $foreign->id],
    ])->assertRedirect(route('inbox.index'));

    expect($selected->fresh())->toBeNull()
        ->and($kept->fresh())->not->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});
