<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 23:58:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddSentryBrowserProfilingHeader
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('Document-Policy', 'js-profiling');

        return $response;
    }
}
