<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-11h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\Masters\MasterAsset\Json\WithProductCodesLookup;
use App\Actions\OrgAction;
use App\Http\Resources\Masters\RelatedMasterProductsCategoriesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetProductCategoriesByCodes extends OrgAction
{
    use WithProductCodesLookup;

    public function handle(Shop $shop, array $codes): AnonymousResourceCollection
    {
        $categories = ProductCategory::where('shop_id', $shop->id)
            ->whereIn(\DB::raw('lower(code)'), $codes)
            ->orderBy('code')
            ->get();

        return RelatedMasterProductsCategoriesResource::collection($categories);
    }

    public function asController(Shop $shop, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->requestedCodes());
    }
}
