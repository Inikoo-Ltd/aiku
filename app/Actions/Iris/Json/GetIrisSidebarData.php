<?php

namespace App\Actions\Iris\Json;

use App\Helpers\WebsiteLanguage;
use App\Helpers\WebsiteTexts;
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
        $website = $request->input('website');

        if (!$website) {
            return response()->json([
                'sidebar' => []
            ]);
        }

        $cacheKey = self::cacheKey($website->id, WebsiteLanguage::translateTo());
        $ttl      = config('iris.cache.iris_website_data_ttl');

        $compute = function () use ($website) {
            $sidebarLayout = Arr::get($website->published_layout, 'sidebar', []);
            $isSidebarActive = Arr::get($sidebarLayout, 'status');

            $irisProductCategoryNavigation =
                GetIrisProductCategoryNavigation::run($website);

            return [
                'sidebar' => array_merge(
                    $isSidebarActive == 'active' ? WebsiteTexts::forVisitor($website->id, Arr::get($website->published_layout, 'sidebar', [])) : [],
                    ['product_categories' => $irisProductCategoryNavigation]
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

    /**
     * The shop's own language keeps the key it always had.
     */
    public static function cacheKey(int $websiteId, ?string $translateTo): string
    {
        return "irisData:website:$websiteId:sideBar".($translateTo ? ":$translateTo" : '');
    }
}
