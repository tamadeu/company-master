<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'unreadInboxCount' => fn () => $request->user()?->inboxMessages()->whereNull('read_at')->count() ?? 0,
                'unreadSalesNotificationCount' => fn () => $request->user()?->inboxMessages()->where('category', 'sale')->whereNull('read_at')->count() ?? 0,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'daySummary' => fn () => $request->session()->get('daySummary'),
            ],
        ];
    }
}
