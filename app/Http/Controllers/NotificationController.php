<?php

namespace App\Http\Controllers;

use App\Models\InboxMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $messages = $request->user()->inboxMessages()
            ->where('category', 'sale')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $request->user()->inboxMessages()
            ->where('category', 'sale')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $messages->through(fn (InboxMessage $message) => [
                'id' => $message->id,
                'subject' => $message->subject,
                'body' => $message->body,
                'actionUrl' => $message->action_url,
                'metadata' => $message->metadata,
                'wasUnread' => $message->read_at === null,
                'createdAt' => $message->created_at->toIso8601String(),
            ]),
        ]);
    }
}
