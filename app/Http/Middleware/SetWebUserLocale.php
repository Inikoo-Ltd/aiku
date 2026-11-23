<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Aug 2025 10:45:47 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\CRM\WebUser;
use App\Helpers\WebsiteLanguage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

//Todo: Remove or refactor this so is compatible with varnish
class SetWebUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        $shop = $request->input('website')->shop;

        /** @var WebUser $webUser */
        if ($webUser = auth()->user()) {
            $locale = $webUser->language?->code;
        } elseif ($shop->showsWebsiteIn($request->query('locale'))) {
            $locale = $request->query('locale');
        } else {
            $locale = $request->cookie('aiku_guest_locale');
        }

        $isAllowed = $webUser ? $shop->offersLanguageTo($webUser->customer, $locale) : $shop->showsWebsiteIn($locale);

        if (!is_string($locale) || ($shop->restrictsLanguages() && !$isAllowed)) {
            $locale = $shop->language->code;
        }

        Cookie::queue('aiku_guest_locale', $locale, 60 * 24 * 120);
        app()->setLocale($locale);
        WebsiteLanguage::set($shop, $locale);

        return $next($request);
    }
}
