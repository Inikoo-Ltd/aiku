<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class RetinaLogout
{
    use AsController;


    public function handle(Request $request): Response
    {
        Auth::guard('retina')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Session::put('reloadLayout', '1');

        Cookie::queue(Cookie::forget('iris_vue'));

        return Redirect::back();

    }

}
