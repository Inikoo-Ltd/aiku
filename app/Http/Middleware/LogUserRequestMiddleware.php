<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:52:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Analytics\UserRequest\ProcessUserRequest;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;

class LogUserRequestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.log_user_requests')) {
            return $next($request);
        }

        if (!str_starts_with($request->route()->getName(), 'grp.') || $request->route()->getName() == 'grp.logout') {
            return $next($request);
        }


        $ip          = $request->ip();
        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        /* @var User $user */
        $user = $request->user();

        if (!app()->runningUnitTests() && $user) {
            ProcessUserRequest::dispatch(
                $user,
                now(),
                [
                    'name'      => $request->route()->getName(),
                    'arguments' => $request->route()->originalParameters(),
                    'url'       => $request->path(),
                ],
                $ip,
                $request->header('User-Agent'),
                $geoLocation
            );
        }

        return $next($request);
    }
}
