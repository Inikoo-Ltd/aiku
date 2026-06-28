<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>  
 * Created: Mon, 29 Jun 2026 01:59:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\Website\BlockedCountries\CheckIfCountryRegionsIsBlocked;

use Closure;
use Illuminate\Http\Request;

class RestrictCountryRegions
{
    public function handle(Request $request, Closure $next)
    {

        if ($request->expectsJson()) {
            return $next($request);
        }

        $isBlocked=CheckIfCountryRegionsIsBlocked::run($request);
        if ($isBlocked) {
            //abort(403);
        }


        return $next($request);
    }
}
