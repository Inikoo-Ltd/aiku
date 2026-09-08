<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:52:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\UserRequest\ProcessUserRequest;
use Illuminate\Support\Facades\Cache;
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

        if ($user) {
            rescue(fn () => Cache::put('staff-last-active:'.$user->id, now()->timestamp, now()->addHours(2)), report: false);
        }

        if ($user) {
            rescue(fn () => $this->recordUserRequest($request, $user, $ip, $geoLocation));
        }

        return $next($request);
    }

    protected function recordUserRequest(Request $request, User $user, string $ip, array $geoLocation): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        \Sentry\traceMetrics()->count(
            'aiku.visit',
            1,
            [
                    'form_factors' => request()->header('sec-ch-ua-form-factors'),
                    'country'      => request()->header('CF-IPCountry') ?? 'XX'

                ]
        );

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
        )->delay(now()->addSeconds(5));
    }
}
