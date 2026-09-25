<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 02:20:53 Mex Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Traits\WithIrisAuthCookie;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class RetinaAuthenticate extends Middleware
{
    use WithIrisAuthCookie;

    public function handle($request, \Closure $next, ...$guards)
    {

        $this->authenticate($request, $guards);

        if ($request->user()?->status === false) {
            $this->auth->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->forgetIrisAuthCookie();
            $this->unauthenticated($request, $guards);
        }

        return $next($request);
    }
    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            return route('retina.login.show');
        }
        return null;
    }
}
