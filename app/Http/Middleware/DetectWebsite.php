<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 17:51:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Http\Middleware\Concerns\DetectsWebsite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectWebsite
{
    use DetectsWebsite;

    public function handle(Request $request, Closure $next): Response
    {
        $website = DetectWebsiteFromDomain::run($request->getHost());
        if ($website === null) {
            abort(404, 'Not found');
        }

        $request->merge($this->getWebsiteBaseData($website));

        return $next($request);
    }
}
