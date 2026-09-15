<?php

/*
 * Author: Vika Aqordi
 * Created on 14-09-2026-10h-18m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\WithProductRecommendationScope;
use App\Http\Resources\Catalogue\IrisProductTrendResource;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Workshop preview of the trends recommender: the same ranking the storefront gets, scoped from the
 * webpage being edited instead of from the storefront request. Family webpages never show trends, so
 * the preview comes back empty for them too.
 */
class GetProductTrendsInWorkshop extends OrgAction
{
    use WithProductRecommendationScope;

    public function handle(Webpage $webpage): Collection
    {
        if ($this->isFamilyWebpage($webpage)) {
            return collect();
        }

        return GetIrisProductTrends::run($webpage->shop, $this->getRecommendationScope($webpage));
    }

    public function asController(Webpage $webpage, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductTrendResource::collection($products);
    }
}
