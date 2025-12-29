<?php

namespace App\Actions\Iris\Json;

use Throwable;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Catalogue\ProductCategory\Json\GetIrisProductCategoryNavigation;

class GetIrisSidebarData
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var Website|null $website */
        $website = $request->get('website');

        if (!$website) {
            return response()->json([
                'sidebar' => []
            ]);
        }

        $cacheKey = "iris:sidebar:website:{$website->id}";
        $ttl      = (int)(config('iris.cache.iris_sidebar_ttl') ?? 600);

        $compute = function () use ($website) {
            $sidebarLayout   = Arr::get($website->published_layout, 'sidebar', []);
            $isSidebarActive = Arr::get($sidebarLayout, 'status');

            $irisProductCategoryNavigation =
                GetIrisProductCategoryNavigation::run($website);

            return [
                'sidebar' => array_merge(
                    $isSidebarActive === 'active' ? $sidebarLayout : [],
                    [
                        'product_categories' => $irisProductCategoryNavigation
                    ]
                )
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
