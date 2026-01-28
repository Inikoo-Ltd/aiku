<?php

/*
 * author Louis Perez
 * created on 15-01-2026-10h-19m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Middleware;

use Closure;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class EnforcesTwoFAMiddleware
{
    public function handle($request, Closure $next)
    {
        // Ignore in Local
        if (app()->isLocal()) {
            return $next($request);
        }

        $authenticator = new Authenticator($request);

        $is_two_factor_required = request()->user()?->is_two_factor_required;

        if (!$is_two_factor_required) {
            return $next($request);
        }

        if ($authenticator->isActivated()) {
            return $next($request);
        }

        return redirect()->route('grp.login.require2fa');
    }
}
