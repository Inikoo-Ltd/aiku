<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Enums\CRM\Poll\PollOptionReferralSourcesEnum;
use Closure;
use Illuminate\Http\Request;

class CaptureReferralSource
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route() ? $request->route()->getName() : null;

        // Allow only if route name starts with 'iris' or is one of the specified retina routes
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

        // Check both referer and current full URL
        $referer = $request->headers->get('referer', '');
        $fullUrl = $request->fullUrl();

        // Use your detection logic
        $source = PollOptionReferralSourcesEnum::detectFromUrl($referer)
            ?? PollOptionReferralSourcesEnum::detectFromUrl($fullUrl);

        // Store in session if detected
        if ($source) {
            session(['referral_source' => $source]);
        }

        return $next($request);
    }
}
