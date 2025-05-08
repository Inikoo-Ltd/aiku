<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 16:47:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HandleInertiaCrossToRetina
{
    public function handle(Request $request, Closure $next)
    {

        if ($this->requiresLocationVisit($request)) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
    public function requiresLocationVisit(Request $request): bool
    {
        if (! $request->headers->has('X-Inertia')) {

            return false;
        }

        if ($request->method() !== $request::METHOD_GET) {

            return false;
        }

        $previousPath = parse_url(url()->previous(), PHP_URL_PATH);

        if ($this->isIris($previousPath)) {
            return false;
        }

        return true;
    }

    private function isIris(string $path): bool
    {
        return !str_starts_with($path, '/app');
    }

}
