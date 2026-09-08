<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanUseMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->can_use_mcp) {
            abort(403, 'MCP access is not enabled for this user.');
        }

        return $next($request);
    }
}
