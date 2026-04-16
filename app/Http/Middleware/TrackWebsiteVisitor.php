<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Middleware;

use App\Actions\Web\WebsiteVisitor\ProcessWebsiteVisitorTracking;
use Closure;
use Illuminate\Http\Request;

class TrackWebsiteVisitor
{
    public function handle(Request $request, Closure $next)
    {

        if ($this->shouldTrack($request)) {
            ProcessWebsiteVisitorTracking::dispatch(
                $request->session()->getId(),
                $request->input('website'),
                $request->user('retina'),
                $request->userAgent(),
                $request->ips(),
                $request->fullUrl(),
                $request->header('referer'),
            );
        }

        return $next($request);
    }


    protected function shouldTrack(Request $request): bool
    {
        if (app()->environment('local')) {
            return false;
        }

        if (!$request->input('website')) {
            return false;
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
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

        return array_all($excludedRoutes, fn ($excluded) => !str_starts_with($routeName, $excluded));
    }
}
