<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Models\CRM\TrafficSource;
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
            $fullUrl = $request->fullUrl();
            $referer = $request->headers->get('referer', '');

            $trafficSource = null;

            if ($fullUrl) {
                $trafficSource = TrafficSource::detectFromWebsite($website, 'ads', $referer);
            } else {
                $trafficSource = TrafficSource::detectFromWebsite($website, 'organic', $fullUrl);
            }

            // Store in session if detected
            if ($trafficSource) {
                session(['traffic_source_id' => $trafficSource->id]);
            }
        }

        return $next($request);
    }
}
