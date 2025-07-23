<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromUrl;
use App\Models\Web\Website;
use Closure;
use Illuminate\Http\Request;

class CaptureTrafficSource
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route() ? $request->route()->getName() : null;

        // Allow only if the route name starts with 'iris' or is one of the specified retina routes
        $allowedRoutes = [
            'retina.register',
            'retina.register_standalone',
            'retina.register_from_google',
        ];

        if (!($routeName && (str_starts_with($routeName, 'iris') || in_array($routeName, $allowedRoutes)))) {
            return $next($request);
        }
        if (auth()->check()) {
            return $next($request);
        }
        $website = $request->get('website');

        if ($website instanceof Website) {
            // Check both referer and current full URL
            $trafficSourceData = GetTrafficSourceFromUrl::run($request->fullUrl());

            if ($trafficSourceData === null) {
                $trafficSourceData = GetTrafficSourceFromRefererHeader::run($request->headers->get('referer', ''));
            }

            //todo append to a cookie


        }

        return $next($request);
    }
}
