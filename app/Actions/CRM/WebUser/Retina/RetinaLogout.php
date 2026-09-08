<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:57 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\Traits\WithRetinaAuthRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class RetinaLogout
{
    use AsController;
    use WithRetinaAuthRedirect;


    public function handle(Request $request): Response
    {
        Auth::guard('retina')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Session::put('reloadLayout', '1');

        Cookie::queue(Cookie::forget('iris_vua'));

        /* The touch history belongs to the journey that just ended. On a shared browser the next
           person to log in must not inherit it into their own attribution record. */
        Cookie::queue(Cookie::forget('aiku_tsd'));
        Cookie::queue(Cookie::forget('aiku_lts'));

        $storefrontUrl = $this->getRetinaLogoutRedirectUrl($request->input('website'));

        if (!$storefrontUrl) {
            return Redirect::back();
        }

        /* The storefront is served by the iris bundle, which resolves its pages from a different
           root than retina, so inertia must do a full page visit instead of following a redirect. */
        return Inertia::location($storefrontUrl);
    }

}
