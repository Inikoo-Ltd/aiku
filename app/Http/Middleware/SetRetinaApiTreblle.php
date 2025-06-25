<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 15:21:16 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetRetinaApiTreblle
{
    public function handle(Request $request, Closure $next)
    {
        config([
            'treblle.enable'     => config('treblle.retina.enable'),
            'treblle.api_key'    => config('treblle.retina.api_key'),
            'treblle.project_id' => config('treblle.retina.api_key'),
        ]);

        return $next($request);
    }
}
