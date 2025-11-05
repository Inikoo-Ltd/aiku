<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Actions\Iris\RetinaLogWebUserRequest;
use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\SysAdmin\WithLogRequest;
use Closure;
use Illuminate\Http\Request;

class LogWebUserRequestMiddleware
{
    use WithLogRequest;

    public function handle(Request $request, Closure $next)
    {
        if (!RetinaLogWebUserRequest::make()->canLogWebUserRequest()) {
            return $next($request);
        }


        ProcessRetinaWebUserRequest::dispatch(
            $request->user(),
            now(),
            [
                'name'      => $request->route()->getName(),
                'arguments' => $request->route()->originalParameters(),
                'url'       => $request->path(),
            ],
            $request->ip(),
            $request->header('User-Agent')
        );


        return $next($request);
    }
}
