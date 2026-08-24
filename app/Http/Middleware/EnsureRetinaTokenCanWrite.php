<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retina API tokens carry retina:read and optionally retina:write; anything that is
 * not a safe HTTP method needs the write ability. Tokens issued before the split have
 * their abilities backfilled to full access by migration.
 */
class EnsureRetinaTokenCanWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            $token = $request->user()?->currentAccessToken();

            if ($token && !$token->can('retina:write')) {
                abort(403, 'This API token is read only.');
            }
        }

        return $next($request);
    }
}
