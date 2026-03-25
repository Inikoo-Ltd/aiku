<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 13:04:22 Central Indonesia Time, Sanur, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AcceptClientHintsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $clientHints = [
            'Sec-CH-UA',
            'Sec-CH-UA-Full-Version-List',
            'Sec-CH-UA-Mobile',
            'Sec-CH-UA-Model',
            'Sec-CH-UA-Platform',
            'Sec-CH-UA-Platform-Version',
            'Sec-CH-UA-Arch',
            'Sec-CH-UA-Bitness',
            'Sec-CH-UA-Full-Version',
            'Sec-CH-UA-Form-Factors',
            'Sec-CH-UA-WoW64',
        ];

        $response->headers->set('Accept-CH', implode(', ', $clientHints));
        $response->headers->set('Permissions-Policy', 'ch-ua=*, ch-ua-full-version-list=*, ch-ua-mobile=*, ch-ua-model=*, ch-ua-platform=*, ch-ua-platform-version=*, ch-ua-arch=*, ch-ua-bitness=*, ch-ua-full-version=*, ch-ua-form-factors=*, ch-ua-wow64=*');

        return $response;
    }
}
