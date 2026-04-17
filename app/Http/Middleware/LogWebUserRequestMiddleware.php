<?php

/*
 * Author: Arya Permana - Kirin
 * Created: Thu, 09 Jan 2025 15:26
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\SysAdmin\WithLogRequest;
use App\Models\CRM\WebUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class LogWebUserRequestMiddleware
{
    use WithLogRequest;

    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return $next($request);
        }

        if (!$this->canLogWebUserRequest()) {
            return $next($request);
        }

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];
        ProcessRetinaWebUserRequest::run(
            $request->user(),
            now(),
            [
                'name'      => $request->route()->getName(),
                'arguments' => $request->route()->originalParameters(),
                'url'       => $request->path(),
            ],
            $request->ip(),
            $request->header('User-Agent'),
            $geoLocation
        );


        return $next($request);
    }

    public function canLogWebUserRequest(): bool
    {
        if (!config('app.log_user_requests')) {
            return false;
        }

        if (session('from-iris-redirect')) {
            return false;
        }

        /* @var WebUser|null $webUser */
        $webUser = request()->user();

        // If there is an authenticated user from another guard that's not a WebUser, skip logging
        if ($webUser !== null && !($webUser instanceof WebUser)) {
            return false;
        }
        $routeName = request()->route()->getName();

        if (!str_starts_with($routeName, 'retina.') && !str_starts_with($routeName, 'iris.')) {
            return false;
        }

        $skipPrefixes = ['retina.models', 'iris.models', 'retina.webhooks', 'iris.json', 'retina.json', 'iris.catalogue'];
        if ($routeName == 'retina.logout') {
            return false;
        }

        if (array_any($skipPrefixes, fn ($prefix) => str_starts_with($routeName, $prefix))) {
            return false;
        }

        if (request()->route() instanceof Route && request()->route()->getAction('uses') instanceof \Closure) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
