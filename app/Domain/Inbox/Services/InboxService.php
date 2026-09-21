<?php

namespace App\Domain\Inbox\Services;

use App\Models\InboxMessage;
use App\Models\User;

class InboxService
{
    public function sendSystem(User $recipient, string $category, string $subject, string $body, ?string $actionUrl = null, ?array $metadata = null): InboxMessage
    {
        return $recipient->inboxMessages()->create([
            'category' => $category,
            'subject' => $subject,
            'body' => $body,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }
}
