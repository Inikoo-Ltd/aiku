<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddFrameOptionsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!app()->environment('local')) {
            $policy = $response->headers->get('Content-Security-Policy');
            $response->headers->set('Content-Security-Policy', ($policy ? rtrim($policy, '; ').'; ' : '')."frame-ancestors 'self' https://app.aiku.io;");
        }

        return $response;
    }
}
