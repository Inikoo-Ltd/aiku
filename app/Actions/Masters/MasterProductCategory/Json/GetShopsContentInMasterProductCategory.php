<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Masters\MasterProductCategoryShopContentResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetShopsContentInMasterProductCategory extends OrgAction
{
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): Collection
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory);
    }

    public function handle(MasterProductCategory $masterProductCategory): Collection
    {
        return ProductCategory::query()
            ->with(['webpage.website', 'webpage.shop', 'webpage.seoImage'])
            ->select([
                'product_categories.id',
                'product_categories.slug',
                'product_categories.code',
                'product_categories.name',
                'product_categories.description',
                'product_categories.description_title',
                'product_categories.description_extra',
                'product_categories.follow_master',
                'product_categories.webpage_id',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'shops.name as shop_name',
            ])
            ->join('shops', 'shops.id', 'product_categories.shop_id')
            ->where('product_categories.master_product_category_id', $masterProductCategory->id)
            ->orderBy('shops.code')
            ->get();
    }

    public function jsonResponse(Collection $productCategories): AnonymousResourceCollection
    {
        return MasterProductCategoryShopContentResource::collection($productCategories);
    }
}
