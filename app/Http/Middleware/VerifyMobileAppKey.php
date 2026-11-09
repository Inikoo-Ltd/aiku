<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-29m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMobileAppKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $appKey = (string) $request->headers->get('X-Aiku-App-Key', '');

        if (!$this->isKnownAppKey($appKey)) {
            abort(404);
        }

        return $next($request);
    }

    private function isKnownAppKey(string $appKey): bool
    {
        foreach (config('app.mobile_app_keys', []) as $knownAppKey) {
            if (hash_equals($knownAppKey, $appKey)) {
                return true;
            }
        }

        return false;
    }
}
