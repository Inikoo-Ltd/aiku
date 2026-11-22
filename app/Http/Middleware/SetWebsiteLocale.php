<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Aug 2025 10:45:47 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetWebsiteLocale
{
    /**
     * DetectIrisWebsite merges the shop language into the request input as `locale`, so the
     * visitor's choice is read from the raw query string.
     */
    public function handle(Request $request, Closure $next)
    {
        $shop = $request->input('website')->shop;
        parse_str((string) $request->server('QUERY_STRING'), $query);

        if (!array_key_exists('locale', $query)) {
            $locale = $shop->language->code;
        } elseif ($shop->showsWebsiteIn($query['locale'])) {
            $locale = $query['locale'];
        } elseif ($request->isMethod('GET')) {
            return redirect()->to($this->urlWithoutLocale($request))->header('X-Aiku-Cacheable-Redirect', '1');
        } else {
            $locale = $shop->language->code;
        }

        app()->setLocale($locale);

        return $next($request);
    }

    private function urlWithoutLocale(Request $request): string
    {
        $queryParts = array_filter(
            explode('&', (string) $request->server('QUERY_STRING')),
            fn (string $part) => $part !== '' && !preg_match('/^locale(=|\[|%5B|$)/', $part)
        );

        return $request->url().($queryParts ? '?'.implode('&', $queryParts) : '');
    }
}
