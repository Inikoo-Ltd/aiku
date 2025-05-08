<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HandleInertiaCrossToIris
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

        if ($this->isRetina($previousPath)) {
            return false;
        }

        return true;
    }

    private function isRetina(string $path): bool
    {
        return str_starts_with($path, '/app');
    }

}
