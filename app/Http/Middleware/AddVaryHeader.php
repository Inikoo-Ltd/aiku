<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Oct 2025 16:49:53 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddVaryHeader
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);


        $existingVary = $response->headers->get('Vary');
        $varyHeaders = ['X-Logged-Status'];

        if ($existingVary) {
            $varyHeaders = array_merge(explode(', ', $existingVary), $varyHeaders);
        }

        $response->headers->set('Vary', implode(', ', array_unique($varyHeaders)));

        return $response;
    }
}
