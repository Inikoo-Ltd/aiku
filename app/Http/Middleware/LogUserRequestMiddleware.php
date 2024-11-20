<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 15:52:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\SysAdmin\User\StoreUserRequest;
use App\Enums\Elasticsearch\ElasticsearchUserRequestTypeEnum;
use Closure;
use Illuminate\Http\Request;

class LogUserRequestMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {

        if (!str_starts_with($request->route()->getName(), 'grp.')) {
            return $next($request);
        }

        if ($request->route()->getName() == 'grp.logout') {
            return $next($request);
        }


        /* @var \App\Models\SysAdmin\User $user */
        $user = $request->user();

        if (!app()->runningUnitTests() && $user && env('USER_REQUEST_LOGGING')) {
            StoreUserRequest::run(
                now(),
                [
                    'name'      => $request->route()->getName(),
                    'arguments' => $request->route()->originalParameters(),
                    'url'       => $request->path()
                ],
                $request->ip(),
                $request->header('User-Agent'),
                ElasticsearchUserRequestTypeEnum::VISIT->value,
                $user,
            );

            $user->stats()->update(['last_active_at' => now()]);
        }

        return $next($request);
    }
}
