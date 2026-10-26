<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 15:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestoreRefererFromCurrentUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentUrl = (string) $request->headers->get('X-Current-Url', '');

        if (str_starts_with($currentUrl, $request->getSchemeAndHttpHost().'/')) {
            $request->headers->set('referer', $currentUrl);
        }

        return $next($request);
    }
}
