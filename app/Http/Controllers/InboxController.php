<?php

namespace App\Http\Controllers;

use App\Models\InboxMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function show(Request $request, InboxMessage $inboxMessage): Response
    {
        $this->authorizeRecipient($request, $inboxMessage);
        $inboxMessage->update(['read_at' => $inboxMessage->read_at ?? now()]);

        return $this->render($request, $inboxMessage->fresh('sender'));
    }

    public function destroy(Request $request, InboxMessage $inboxMessage): RedirectResponse
    {
        $this->authorizeRecipient($request, $inboxMessage);
        $inboxMessage->delete();

        return redirect()->route('inbox.index')->with('success', 'Mensagem removida da caixa de entrada.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $updated = $request->user()->inboxMessages()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', "{$updated} mensagem(ns) marcada(s) como lida(s).");
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $deleted = $request->user()->inboxMessages()
            ->whereIn('id', $data['ids'])
            ->delete();

        return redirect()->route('inbox.index')->with('success', "{$deleted} mensagem(ns) excluída(s).");
    }

    private function render(Request $request, ?InboxMessage $selected = null): Response
    {
        $messages = $request->user()->inboxMessages()
            ->with('sender:id,name')
            ->latest()
            ->paginate(25)
            ->through(fn (InboxMessage $message) => $this->messageData($message))
            ->withQueryString();

        return Inertia::render('Inbox/Index', [
            'messages' => $messages,
            'selectedMessage' => $selected ? $this->messageData($selected) : null,
        ]);
    }

    private function messageData(InboxMessage $message): array
    {
        return [
            'id' => $message->id,
            'category' => $message->category,
            'subject' => $message->subject,
            'body' => $message->body,
            'actionUrl' => $message->action_url,
            'readAt' => $message->read_at?->toIso8601String(),
            'createdAt' => $message->created_at->toIso8601String(),
            'senderName' => $message->sender?->name ?? 'Sistema ERP Game',
        ];
    }

    private function authorizeRecipient(Request $request, InboxMessage $message): void
    {
        abort_unless($message->recipient_user_id === $request->user()->id, 403);
    }
}
