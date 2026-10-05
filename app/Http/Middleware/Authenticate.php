<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Dec 2023 22:13:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\SysAdmin\User;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Contracts\Auth\StatefulGuard;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards): void
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            $authGuard = $this->auth->guard($guard);

            if (!$authGuard->check()) {
                continue;
            }

            $user = $authGuard->user();
            if ($user instanceof User && !$user->status) {
                if ($authGuard instanceof StatefulGuard) {
                    $authGuard->logout();
                    if ($request->hasSession()) {
                        $request->session()->invalidate();
                    }
                }

                continue;
            }

            $this->auth->shouldUse($guard);

            return;
        }

        $this->unauthenticated($request, $guards);
    }

    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            return route('grp.login.show');
        }
        return null;
    }
}
