<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Aug 2025 10:45:47 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\CRM\WebUser;
use App\Models\Helpers\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SetWebUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        /** @var WebUser $webUser */
        if ($webUser = auth()->user()) {
            /** @var Language $language */
            $language = Language::find($webUser->language_id);
            $locale  = $language->code;

        } else {
            $locale = $request->cookie('aiku_guest_locale');
        }


        if (!$locale) {
            $locale = $request->get('website')->shop->language->code;
        }



        Cookie::queue('aiku_guest_locale', $locale, 60 * 24 * 120);
        app()->setLocale($locale);
        return $next($request);
    }
}
