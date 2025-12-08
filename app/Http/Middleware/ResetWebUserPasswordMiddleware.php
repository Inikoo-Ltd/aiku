<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Oct 2023 15:52:31 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetWebUserPasswordMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user(Auth::getDefaultDriver())->reset_password) {
            return redirect()->route('retina.reset-password.edit');
        }

        return $next($request);
    }
}
