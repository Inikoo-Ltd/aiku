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
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

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

        if ($website) {
            // Check both referer and current full URL
            $trafficSourceData = GetTrafficSourceFromUrl::run($request->fullUrl());

            if ($trafficSourceData === null) {
                $trafficSourceData = GetTrafficSourceFromRefererHeader::run($request->headers->get('referer', ''));
            }

            if ($trafficSourceData) {
                $lastTrafficSource = $request->cookie('aiku_lts');

                if ($lastTrafficSource == $trafficSourceData) {
                    return $next($request);
                }


                // Check if the cookie already exists
                $existingCookieData = $request->cookie('aiku_tsd');
                if ($existingCookieData) {
                    $appendedTrafficSourceData = $existingCookieData.'|'.now()->utc()->timestamp.$trafficSourceData;
                    $cookieSize                = (4 + strlen('aiku_tsd'.$appendedTrafficSourceData)) / 1024;

                    if ($cookieSize > 3.9) {
                        $appendedTrafficSourceData = $this->trimOldestTrafficSource($appendedTrafficSourceData);
                    }
                    Cookie::queue('aiku_tsd', $appendedTrafficSourceData, 60 * 24 * 120);
                } else {
                    Cookie::queue('aiku_tsd', now()->utc()->timestamp.$trafficSourceData, 60 * 24 * 120);
                }
                Cookie::queue('aiku_lts', $trafficSourceData, 60 * 24 * 120);
            }
        }

        return $next($request);
    }

    public function trimOldestTrafficSource($trafficSourceData): string
    {
        $trafficSourceData = explode(',', $trafficSourceData);
        $trafficSourceData = array_slice($trafficSourceData, 1);

        return implode('|', $trafficSourceData);
    }

}
