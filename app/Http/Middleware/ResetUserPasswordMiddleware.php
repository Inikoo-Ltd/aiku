<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Reviewed: Mon, 16 Oct 2023 14:37:39 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetUserPasswordMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user(Auth::getDefaultDriver())->reset_password) {
            return redirect()->route('grp.reset-password.edit');
        }

        return $next($request);
    }
}
