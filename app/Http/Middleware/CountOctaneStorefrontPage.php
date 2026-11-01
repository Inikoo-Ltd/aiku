<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\Web\Website;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts, per website and UTC day, the storefront HTML pages Octane rendered for requests that came
 * through Cloudflare (they carry CF-Ray; Varnish warm-up crawls do not). The nightly edge job stores
 * the count beside Cloudflare's figures, so what reached our servers splits into Varnish and Octane.
 * It runs after the response is sent and never fails a page.
 */
class CountOctaneStorefrontPage
{
    public const int KEEP_SECONDS = 8 * 86400;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $website = $request->input('website');
        if (!$website instanceof Website
            || !$request->headers->has('CF-Ray')
            || !str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $key = self::key(now('UTC'));
        try {
            Redis::pipeline(function ($pipe) use ($key, $website) {
                $pipe->hincrby($key, (string) $website->id, 1);
                $pipe->expire($key, self::KEEP_SECONDS);
            });
        } catch (Throwable) {
            return;
        }
    }

    public static function key(Carbon $day): string
    {
        return 'edge_octane_pages:'.$day->toDateString();
    }
}
