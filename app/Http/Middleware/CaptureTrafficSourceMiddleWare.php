<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-15h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Middleware;

use App\Actions\Iris\CaptureTrafficSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class CaptureTrafficSourceMiddleWare
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(Request $request, Closure $next)
    {

        if(!CaptureTrafficSource::make()->canCaptureTrafficSource()){
            return $next($request);
        }

        $cookies=CaptureTrafficSource::make()->getCookies();

        foreach ($cookies as $key => $value) {
            Cookie::queue($key, $value['value'], $value['duration']);
        }
        return $next($request);
    }



}
