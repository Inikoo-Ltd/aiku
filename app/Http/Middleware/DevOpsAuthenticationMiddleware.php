<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 17:27:33 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevOpsAuthenticationMiddleware
{

    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-DEVOPS-TOKEN');
        abort_if($token !== config('app.devops_token'), 403);

        return $next($request);
    }
}
