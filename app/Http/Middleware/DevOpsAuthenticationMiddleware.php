<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DevOpsAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('X-DEVOPS-TOKEN');
        abort_if($header !== config('app.devops_token'), 403);

        return $next($request);
    }
}
