<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Apr 2026 21:49:18 Malaysia Time, Kathmandu, Nepal
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Support\NewRelic\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class NewRelicTransactionMiddleware
{
    public function __construct(private Agent $agent)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {

        if (!config('newrelic.enabled') || !$this->agent->isLoaded()) {
            return $next($request);
        }

        $appName = trim((string) config('newrelic.app_name'));
        $this->agent->startTransaction($appName);

        $response = $next($request);


        $this->agent->nameTransaction($this->resolveTransactionName($request));
        $this->agent->addCustomParameter('app_env', (string) app()->environment());
        $this->agent->addCustomParameter('request_method', $request->method());
        $this->agent->addCustomParameter('request_path', '/'.ltrim($request->path(), '/'));
        $this->agent->terminateTransaction();
        return $response;
    }

    private function resolveTransactionName(Request $request): string
    {
        $route = $request->route();

        if ($route !== null) {
            $routeName = $route->getName();
            if (is_string($routeName) && $routeName !== '') {
                return $routeName;
            }

            $routeUri = $route->uri();
            if ($routeUri !== '') {
                return sprintf('%s %s', $request->method(), $routeUri);
            }
        }

        return sprintf('%s /%s', $request->method(), ltrim($request->path(), '/'));
    }
}
