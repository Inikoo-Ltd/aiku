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
    /**
     * Requests an open tab makes on its own timer. They prove a tab is open, not that a person is
     * there, so they must not refresh staff-last-active.
     */
    public const BACKGROUND_ROUTES = [
        'grp.chat.staff.coworkers.index',
        'grp.chat.staff.conversations.index',
        'grp.chat.presence.track',
        'grp.chat.inbox_timings.store',
    ];

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

        if ($user && !$this->isBackgroundRequest($request)) {
            rescue(fn () => Cache::put('staff-last-active:'.$user->id, now()->timestamp, now()->addHours(2)), report: false);
        }

        if ($user) {
            rescue(fn () => $this->recordUserRequest($request, $user, $ip, $geoLocation));
        }

        return $next($request);
    }

    /**
     * A tab with no recent mouse or keyboard input sends X-Background-Request, which catches the
     * live-event reloads (tickets, clocking, AI usage) whose routes are ordinary pages.
     */
    protected function isBackgroundRequest(Request $request): bool
    {
        return $request->hasHeader('X-Background-Request')
            || in_array($request->route()->getName(), self::BACKGROUND_ROUTES, true);
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
