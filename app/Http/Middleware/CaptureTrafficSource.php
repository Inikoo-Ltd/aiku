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
use App\Enums\Web\Website\WebsiteTypeEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Response;

class CaptureTrafficSource
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $routeName = $request->route() ? $request->route()->getName() : null;

        if (!$response instanceof Response) {
            return $response;
        }

        $routeName = $request->route() ? $request->route()->getName() : null;

        // Allow only if the route name starts with 'iris' or is one of the specified retina routes
        $allowedRoutes = [
            'retina.register',
            'retina.register_standalone',
            'retina.register_from_google',
            'registration-form',
        ];

        if (!($routeName && (str_starts_with($routeName, 'iris') || in_array($routeName, $allowedRoutes)))) {
            return $next($request);
        }

        $website = $request->get('website');

        if (auth()->check() && $website && $website->type === WebsiteTypeEnum::DROPSHIPPING) {
            return $next($request);
        }

        $trafficSourceSlug = GetTrafficSourceFromUrl::run($request->fullUrl())
            ?? GetTrafficSourceFromRefererHeader::run($request->headers->get('referer', ''));

        if ($trafficSourceSlug) {
            $timestampedEntry = now()->utc()->timestamp . '|' . $trafficSourceSlug;

            $existingCookieData = $request->cookie('aiku_tsd');
            $lastTrafficSource  = $request->cookie('aiku_lts');

            if ($lastTrafficSource === $trafficSourceSlug) {
                return $response;
            }

            $entries = $existingCookieData ? explode('||', $existingCookieData) : [];
            $entries[] = $timestampedEntry;

            // Pangkas jika ukuran cookie terlalu besar
            $cookieSize = (4 + strlen('aiku_tsd' . implode('||', $entries))) / 1024;
            if ($cookieSize > 3.9) {
                $entries = $this->trimOldestTrafficSources($entries);
            }

            $newCookieValue = implode('||', $entries);
            $response->cookie('aiku_tsd', $newCookieValue, 60 * 24 * 120);
            $response->cookie('aiku_lts', $trafficSourceSlug, 60 * 24 * 120);
        }

        return $response;
    }

    public function trimOldestTrafficSources(array $entries): array
    {
        // Buang entri pertama (paling lama)
        return array_slice($entries, 1);
    }
}
