<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\SysAdmin\WithLogRequest;
use App\Models\CRM\WebUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class LogWebUserRequestMiddleware
{
    use WithLogRequest;
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.log_user_requests')) {
            return $next($request);
        }

        $routeName = $request->route()->getName();

        if (!str_starts_with($routeName, 'retina.') && !str_starts_with($routeName, 'iris.')) {
            return $next($request);
        }

        $skipPrefixes = ['retina.models', 'retina.webhooks', 'iris.json', 'retina.json'];
        if ($routeName == 'retina.logout') {
            return $next($request);
        }

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        if ($request->route() instanceof Route && $request->route()->getAction('uses') instanceof \Closure) {
            return $next($request);
        }

        /* @var WebUser $webUser */
        $webUser = $request->user();


        if (!app()->runningUnitTests() && $webUser && $webUser instanceof WebUser) {

            $ip = $request->ip();
            ProcessRetinaWebUserRequest::dispatch(
                $webUser,
                now(),
                [
                    'name'      => $request->route()->getName(),
                    'arguments' => $request->route()->originalParameters(),
                    'url'       => $request->path(),
                ],
                $ip,
                $request->header('User-Agent')
            );

        }

        return $next($request);
    }
}
