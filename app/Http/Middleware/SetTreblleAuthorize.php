<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Middleware;

use App\Models\CRM\Customer;
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
        /** @var Customer $customer */
        $customer = $request->user();

        $apiKey = Arr::get($customer->shop->settings, 'treblle.api_key');
        $projectId = Arr::get($customer->shop->settings, 'treblle.project_id');

        if ($apiKey && $projectId) {
            config([
                'treblle.api_key' => $apiKey,
                'treblle.project_id' => $projectId,
            ]);
        }

        return $next($request);
    }
}
