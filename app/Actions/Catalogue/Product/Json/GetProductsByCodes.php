<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-11h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\Masters\MasterAsset\Json\WithProductCodesLookup;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetProductsByCodes extends OrgAction
{
    use WithProductCodesLookup;

    public function handle(ProductCategory $productCategory, array $codes): AnonymousResourceCollection
    {
        if ($productCategory->type !== ProductCategoryTypeEnum::FAMILY) {
            abort(403, 'Only families keep a product index');
        }

        return $this->lookup(
            Product::where('family_id', $productCategory->id)->orderBy('index_under_family'),
            $codes
        );
    }

    public function inShop(Shop $shop, array $codes): AnonymousResourceCollection
    {
        return $this->lookup(Product::where('shop_id', $shop->id)->orderBy('code'), $codes);
    }

    private function lookup(Builder $query, array $codes): AnonymousResourceCollection
    {
        return ProductsResource::collection($query->whereIn(\DB::raw('lower(code)'), $codes)->get());
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->requestedCodes());
    }

    public function inShopController(Shop $shop, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->inShop($shop, $this->requestedCodes());
    }
}
