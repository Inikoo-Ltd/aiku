<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Middleware;

use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ElevateWebsiteChatStateful
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->needsVisitorLogin($request)) {
            $host     = $request->getHost();
            $stateful = config('sanctum.stateful', []);

            if (!in_array($host, $stateful, true) && $this->isPortalDomain($host)) {
                config(['sanctum.stateful' => array_merge($stateful, [$host])]);
            }
        }

        return $next($request);
    }

    /**
     * Posts that arrive with a session cookie are read with the visitor's login. They are left out
     * of the CSRF check (VerifyCsrfToken::$except): the session cookie is SameSite=lax, so a post
     * from another site never carries the login, and a stale token would refuse the message.
     */
    private function needsVisitorLogin(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            return $request->is('app/api/chats/sessions');
        }

        return $request->isMethod('POST')
            && $request->hasCookie(config('session.cookie'))
            && $request->is('app/api/chats/sessions', 'app/api/chats/offline-message', 'app/api/chats/messages/*/send');
    }

    private function isPortalDomain(string $host): bool
    {
        return Cache::remember(
            'chat_stateful_portal_domain:'.$host,
            now()->addMinutes(10),
            function () use ($host): bool {
                try {
                    return DetectWebsiteFromDomain::make()->handle($host) !== null;
                } catch (\Throwable) {
                    return false;
                }
            }
        );
    }
}
