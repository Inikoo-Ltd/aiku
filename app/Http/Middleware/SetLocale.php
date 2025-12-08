<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 09 Sept 2022 03:35:05 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->getLocale();

        Cookie::queue('aiku_language', $locale, 60 * 8);
        session(['aiku_language' => $locale]);
        app()->setLocale($locale);

        return $next($request);
    }

    public function getLocale(): string
    {

        try {
            $locale = session()->get('aiku_language', Cookie::get('aiku_language'));
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface) {
            $locale = null;
        }
        if ($locale) {
            return $locale;
        }

        /** @var User $user */
        if ($user = auth()->user()) {
            /** @var Language $language */
            $language = Language::find($user->language_id);
            $locale = $language->code;
        } else {
            $locale = substr(locale_accept_from_http(Arr::get($_SERVER, 'HTTP_ACCEPT_LANGUAGE', 'en')), 0, 2);
        }

        if (! $locale) {
            $locale = 'en';
        }

        return $locale;
    }
}
