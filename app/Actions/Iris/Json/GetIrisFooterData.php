<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 11:58:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use Throwable;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisFooterData
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var Website|null $website */
        $website = $request->input('website');

        if (!$website) {
            return response()->json([
                'footer' => []
            ]);
        }

        $cacheKey = "iris:footer:website:{$website->id}";
        $ttl      = (int)(config('iris.cache.iris_website_data_ttl') ?? 900);

        $compute = function () use ($website) {
            $footerLayout   = Arr::get($website->published_layout, 'footer');
            $isFooterActive = Arr::get($footerLayout, 'status');

            return [
                'footer' => array_merge(
                    $isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : [],
                ),
            ];
        };

        try {
            $data = Cache::remember($cacheKey, $ttl, $compute);
        } catch (Throwable) {
            $data = $compute();
        }

        return response()->json($data);
    }
}
