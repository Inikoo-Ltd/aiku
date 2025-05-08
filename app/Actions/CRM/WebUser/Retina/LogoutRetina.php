<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LogoutRetina
{
    use AsController;


    public function handle(Request $request): Response
    {
        Auth::guard('retina')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Session::put('reloadLayout', '1');

        // return Redirect::route('retina.login.show');  // No refresh page
        return Inertia::location(route('retina.login.show'));  // Refresh

    }

}
