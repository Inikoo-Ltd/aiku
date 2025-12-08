<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Dec 2023 23:03:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetHanAsAppScope
{
    public function handle(Request $request, Closure $next)
    {
        app()->bind('app.scope', function () {
            return 'han';
        });

        return $next($request);
    }
}
