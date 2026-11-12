<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 02:20:53 Mex Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class RetinaAuthenticate extends Middleware
{
    /**
     * Pages a logged-out visitor is sent back to after logging in, with their query string
     * (campaign attribution) intact. Only these routes are carried through login, so a link
     * can never turn the login page into a redirect to somewhere else. The #anchor never
     * reaches the server; the browser keeps it across the redirect and the login page puts
     * it back.
     */
    public const RETURN_AFTER_LOGIN_ROUTES = [
        'retina.dashboard.show',
    ];

    public function handle($request, \Closure $next, ...$guards)
    {

        $this->authenticate($request, $guards);

        return $next($request);
    }

    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            $ref = $this->getReturnPath($request);

            return route('retina.login.show', $ref ? ['ref' => $ref] : []);
        }

        return null;
    }

    /**
     * Only a link that carries a query string (a campaign link) is returned to, so a plain
     * visit to one of these pages keeps the usual post-login destination.
     */
    protected function getReturnPath(Request $request): ?string
    {
        if (!$request->isMethod('GET') || !in_array($request->route()?->getName(), static::RETURN_AFTER_LOGIN_ROUTES, true)) {
            return null;
        }

        $query = $request->getQueryString();
        if (!$query) {
            return null;
        }

        return '/'.ltrim($request->path(), '/').'?'.$query;
    }
}
