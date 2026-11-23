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
    public const string ADDRESS_LOCALE_SESSION_KEY = 'retina_address_locale';

    public function handle(Request $request, Closure $next)
    {
        $shop = $request->input('website')->shop;

        /** @var WebUser $webUser */
        if ($webUser = auth()->user()) {
            $locale = $this->addressLocaleFor($request, $webUser) ?? $webUser->language?->code;
        } elseif ($shop->showsWebsiteIn($request->query('locale'))) {
            $locale = $request->query('locale');
            if ($request->hasSession()) {
                $request->session()->put(self::ADDRESS_LOCALE_SESSION_KEY, $locale);
            }
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

    /**
     * A signed-in customer who opens /app with ?locale (e.g. from a German page of the website) reads it in that
     * language for the rest of the session, when the shop offers it to them; their profile language is left as it is.
     */
    private function addressLocaleFor(Request $request, WebUser $webUser): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $shop          = $webUser->shop;
        $addressLocale = $request->query('locale');

        if (is_string($addressLocale) && $shop->offersLanguageTo($webUser->customer, $addressLocale)) {
            $request->session()->put(self::ADDRESS_LOCALE_SESSION_KEY, $addressLocale);
        }

        $sessionLocale = $request->session()->get(self::ADDRESS_LOCALE_SESSION_KEY);

        if (is_string($sessionLocale) && $shop->offersLanguageTo($webUser->customer, $sessionLocale)) {
            return $sessionLocale;
        }

        $request->session()->forget(self::ADDRESS_LOCALE_SESSION_KEY);

        return null;
    }
}
