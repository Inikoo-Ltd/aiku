<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 04:33:06 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DisableSSR
{
    public function handle(Request $request, Closure $next)
    {
        config(['inertia.ssr.enabled' => false]);
        return $next($request);
    }
}
