<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Middleware;

use App\Actions\Web\WebsiteVisitor\ProcessWebsiteVisitorTracking;
use Closure;
use hisorange\BrowserDetect\Parser;
use Illuminate\Http\Request;

class TrackWebsiteVisitor
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if ($this->shouldTrack($request)) {
            ProcessWebsiteVisitorTracking::dispatch(
                sessionId: $request->session()->getId(),
                website: $request->get('website'),
                webUser: $request->user('retina'),
                userAgent: $request->userAgent(),
                ips: $request->ips(),
                currentUrl: $request->fullUrl(),
                referrer: $request->header('referer'),
            );
        }
    }

    protected function shouldTrack(Request $request): bool
    {
        if (app()->environment('local')) {
            return false;
        }

        if (!$request->get('website')) {
            return false;
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return false;
        }

        if ($this->isBot($request)) {
            return false;
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return false;
        }

        $excludedRoutes = [
            'iris.models',
            'retina.models',
            'iris.json',
            'retina.json',
            'iris.webhooks',
            'retina.webhooks',
        ];

        foreach ($excludedRoutes as $excluded) {
            if (str_starts_with($routeName, $excluded)) {
                return false;
            }
        }

        return true;
    }

    protected function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent();

        if (!$userAgent) {
            return false;
        }

        $parsedUserAgent = (new Parser())->parse($userAgent);
        $deviceType = $parsedUserAgent->deviceType();

        return strtolower($deviceType) === 'bot';
    }
}
