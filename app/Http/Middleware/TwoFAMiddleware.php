<?php

/*
 * author Louis Perez
 * created on 13-01-2026-15h-03m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Middleware;

use Closure;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class TwoFAMiddleware
{
    public function handle($request, Closure $next)
    {
        // Ignore in Local
        if (app()->isLocal()) {
            return $next($request);
        }

        $authenticator = new Authenticator($request);
        // Allow user to go to where they want to if authenticated
        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        // Redirect to 2FA if they are not authenticated and try to access another page
        return redirect()->route('grp.login.show2fa');
    }
}
