<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Middleware;

use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SetTreblleAuthorize
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $model = $request->user();

        if ($model instanceof CustomerSalesChannel) {
            $apiKey = Arr::get($model->shop->settings, 'treblle.api_key');
            $projectId = Arr::get($model->shop->settings, 'treblle.project_id');

            if ($apiKey && $projectId) {
                config([
                    'treblle.api_key' => $apiKey,
                    'treblle.project_id' => $projectId,
                ]);
            }
        } elseif ($model instanceof User) {
            $apiKey = Arr::get($model->group->settings, 'treblle.api_key');
            $projectId = Arr::get($model->group->settings, 'treblle.project_id');

            if ($apiKey && $projectId) {
                config([
                    'treblle.api_key' => $apiKey,
                    'treblle.project_id' => $projectId,
                ]);
            }
        }


        return $next($request);
    }
}
