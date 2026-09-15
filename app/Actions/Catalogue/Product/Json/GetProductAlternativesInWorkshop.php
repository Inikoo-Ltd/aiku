<?php

/*
 * Author: Vika Aqordi
 * Created on 14-09-2026-10h-21m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\IrisProductAlternativeResource;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * Workshop preview of the item detail alternatives recommender. The input product is the model of the
 * webpage being edited, so the block only has something to show on a product webpage.
 */
class GetProductAlternativesInWorkshop extends OrgAction
{
    public function handle(Webpage $webpage): Collection
    {
        $product = $webpage->model;

        if (!$product instanceof Product) {
            return collect();
        }

        return GetIrisProductAlternatives::run($product);
    }

    public function asController(Webpage $webpage, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductAlternativeResource::collection($products);
    }
}
