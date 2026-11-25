<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravel keys `throttle:N,M` on the signed-in user alone, so every such route shared one counter
 * per user: the chat polling its conversation every 1.5 seconds used up the 6 a minute of the
 * Talk to our team button, which then answered 429 (MER-09). Without a prefix each route now
 * counts on its own; `throttle:N,M,name` still shares a counter between the routes that name it,
 * and named limiters are untouched.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (is_string($maxAttempts) && func_num_args() === 3 && $this->limiter->limiter($maxAttempts)) {
            return parent::handle($request, $next, $maxAttempts);
        }

        if ($prefix === '') {
            $route  = $request->route();
            $prefix = ($route?->getName() ?? $route?->uri() ?? '').'|';
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
