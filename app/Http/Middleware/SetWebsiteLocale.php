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
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->input('locale');
        if (!$locale) {
            $locale = $request->input('website')->shop->language->code;
        }
        app()->setLocale($locale);
        return $next($request);

    }
}
