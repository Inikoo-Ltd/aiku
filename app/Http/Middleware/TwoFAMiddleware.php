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
        $authenticator = new Authenticator($request);
        
        // Importantly, all of this check is needed, otherwise it will break and causes infinity loop. Please do not remove any of them
        $isAccessing2FA = $request->routeIs('grp.login.show2fa', 'grp.login.auth2fa');

        // Block user from accessing 2FA page if already validated
        if ($authenticator->isAuthenticated() && $isAccessing2FA) {
            return redirect()->route('grp.dashboard.show');
        }

        // Allow user to go to where they want to if authenticated
        if ($authenticator->isAuthenticated()) {
            return $next($request);
        }

        // Prompt user to access 2FA if not authenticated
        if ($isAccessing2FA) {
            return $next($request);
        }

        // Redirect to 2FA if they are not authenticated and try to access another page
        return redirect()->route('grp.login.show2fa');
    }
}
