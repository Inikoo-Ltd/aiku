<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetGrpApiTreblle
{
    public function handle(Request $request, Closure $next)
    {
        config([
            'treblle.enable'     => config('treblle.grp.enable'),
            'treblle.api_key'    => config('treblle.grp.api_key'),
            'treblle.project_id' => config('treblle.grp.api_key'),
        ]);


        return $next($request);
    }
}
