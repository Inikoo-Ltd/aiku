<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Sep 2023 16:31:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Web\Website\WebsiteStateEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWebsiteState
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $website = $request->get('website');

        $status = $website->status;

        if ($status) {
            return $next($request);
        } else {
            $url = '';
            $status = 307;

            switch ($website->state) {
                case WebsiteStateEnum::LIVE:
                    $url = 'disclosure/maintenance';
                    if ($request->route()->getName() == 'iris.disclosure.maintenance') {
                        return $next($request);
                    }
                    break;
                case WebsiteStateEnum::IN_PROCESS:
                    $url = 'disclosure/under-construction';
                    if ($request->route()->getName() == 'iris.disclosure.under-construction') {
                        return $next($request);
                    }

                    break;
                case WebsiteStateEnum::CLOSED:
                    if ($request->route()->getName() == 'iris.disclosure.closed') {
                        return $next($request);
                    }
                    $url = 'disclosure/closed';
                    $status = 308;
                    break;
            }

            if (! $url) {
                return $next($request);
            }

            return redirect($url, $status);
        }
    }
}
