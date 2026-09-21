<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InboxMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminMessageController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Index', [
            'section' => 'messages',
            'recipients' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'is_admin']),
            'records' => InboxMessage::query()
                ->where('sender_user_id', $request->user()->id)
                ->with('recipient:id,name,email')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['user', 'all_players'])],
            'recipient_user_id' => ['nullable', 'required_if:audience,user', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'action_url' => ['nullable', 'string', 'max:500', 'starts_with:/'],
        ]);

        $recipientIds = $data['audience'] === 'all_players'
            ? User::query()->where('is_admin', false)->pluck('id')
            : collect([(int) $data['recipient_user_id']]);

        if ($recipientIds->isEmpty()) {
            throw ValidationException::withMessages(['audience' => 'Nenhum jogador disponível para receber a mensagem.']);
        }

        $now = now();
        DB::table('inbox_messages')->insert($recipientIds->map(fn (int $recipientId) => [
            'recipient_user_id' => $recipientId,
            'sender_user_id' => $request->user()->id,
            'category' => 'admin',
            'subject' => $data['subject'],
            'body' => $data['body'],
            'action_url' => $data['action_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return back()->with('success', $recipientIds->count() === 1
            ? 'Mensagem enviada.'
            : "Mensagem enviada para {$recipientIds->count()} jogadores.");
    }
}
