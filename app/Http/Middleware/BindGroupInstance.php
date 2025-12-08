<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Dec 2023 23:03:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Cache;
use Closure;
use Illuminate\Http\Request;

class BindGroupInstance
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $group = Cache::remember('bound-group-'.$request->user()->id, 3600, function () use ($request) {
                return $request->user()->group;
            });

            app()->instance('group', $group);
            setPermissionsTeamId($group->id);
        }

        return $next($request);
    }
}
