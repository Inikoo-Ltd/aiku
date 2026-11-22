<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 11:58:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Json;

use Throwable;
use App\Actions\Web\MakeLayoutLinksRootRelative;
use App\Actions\Web\RefreshGrpAssetUrls;
use App\Helpers\WebsiteLanguage;
use App\Helpers\WebsiteTexts;
use App\Models\DevOps\AppDeployment;
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

        $cacheKey = self::cacheKey($website->id, WebsiteLanguage::translateTo());
        $ttl      = config('iris.cache.iris_website_data_ttl');


        $compute = function () use ($website) {
            $footerLayout   = Arr::get($website->published_layout, 'footer');
            $isFooterActive = Arr::get($footerLayout, 'status');

            return [
                'footer' => MakeLayoutLinksRootRelative::run(
                    RefreshGrpAssetUrls::run(
                        $isFooterActive == 'active' ? WebsiteTexts::forVisitor($website->id, Arr::get($website->published_layout, 'footer')) : [],
                    )
                ),
            ];
        };

        try {
            $data = Cache::remember($cacheKey, $ttl, $compute);
        } catch (Throwable) {
            $data = $compute();
        }

        $data['version']   = AppDeployment::latest()->first()?->semantic_version;
        $data['shop_name'] = $website->shop?->name;

        return response()->json($data);
    }

    /**
     * The shop's own language keeps the key it always had.
     */
    public static function cacheKey(int $websiteId, ?string $translateTo): string
    {
        return "irisData:website:$websiteId:footer".($translateTo ? ":$translateTo" : '');
    }
}
