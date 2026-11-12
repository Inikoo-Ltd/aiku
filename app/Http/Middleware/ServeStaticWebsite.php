<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\StaticWebsite\Iris\ServeStaticWebsite as ServeStaticWebsiteAction;
use App\Models\Web\StaticWebsite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before routing so an info website owns every path on its domain, including /_assets/ and
 * paths the shop routes would otherwise claim. Aiku's own hosts skip the lookup entirely.
 */
class ServeStaticWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appDomain = strtolower((string) config('app.domain'));

        if ($appDomain !== '' && ($host === $appDomain || str_ends_with($host, '.'.$appDomain))) {
            return $next($request);
        }

        $domain = preg_replace('/^(www|v2)\./', '', $host);
        $staticWebsiteId = StaticWebsite::liveDomains()[$domain] ?? null;

        if (!$staticWebsiteId) {
            return $next($request);
        }

        $staticWebsite = StaticWebsite::find($staticWebsiteId);
        if (!$staticWebsite) {
            return $next($request);
        }

        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return ServeStaticWebsiteAction::run($staticWebsite, $request);
    }
}
