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
        if ($request->isMethod('GET') && $request->is('app/api/chats/sessions')) {
            $host     = $request->getHost();
            $stateful = config('sanctum.stateful', []);

            if (!in_array($host, $stateful, true) && $this->isPortalDomain($host)) {
                config(['sanctum.stateful' => array_merge($stateful, [$host])]);
            }
        }

        return $next($request);
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
