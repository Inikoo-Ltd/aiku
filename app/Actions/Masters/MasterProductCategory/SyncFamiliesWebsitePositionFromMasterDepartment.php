<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncFamiliesWebsitePositionFromMasterDepartment
{
    use AsAction;

    /**
     * Copies the hand picked order of the master families onto every shop family following them,
     * then drops the caches of the pages listing those families so the new order is served.
     */
    public function handle(MasterProductCategory $masterDepartment): void
    {
        $masterFamilyIds = MasterProductCategory::where('master_department_id', $masterDepartment->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->pluck('id');

        if ($masterFamilyIds->isEmpty()) {
            return;
        }

        $followingShopIds = $this->getFollowingShopIds($masterDepartment);

        if ($followingShopIds->isEmpty()) {
            return;
        }

        DB::table('product_categories')
            ->whereIn('master_product_category_id', $masterFamilyIds)
            ->whereIn('shop_id', $followingShopIds)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->update([
                'website_position' => DB::raw('(select mpc.website_position from master_product_categories mpc where mpc.id = product_categories.master_product_category_id)')
            ]);

        foreach ($this->getListingWebpages($masterFamilyIds, $followingShopIds) as $webpage) {
            BreakWebpageCache::dispatch($webpage, false);
        }
    }

    /**
     * Only the shops that have not opted out of following the master family order.
     *
     * @return Collection<int, int>
     */
    private function getFollowingShopIds(MasterProductCategory $masterDepartment): Collection
    {
        return $masterDepartment->masterShop->shops
            ->filter(fn ($shop) => data_get($shop->settings, 'catalog.family_order_follow_master', true))
            ->pluck('id')
            ->values();
    }

    /**
     * The department, sub department and collection pages of every shop that lists one of these
     * families, gathered in one go: a department carries hundreds of families and they all share
     * the same handful of listing pages.
     *
     * @param Collection<int, int> $masterFamilyIds
     * @param Collection<int, int> $shopIds
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Webpage>
     */
    private function getListingWebpages(Collection $masterFamilyIds, Collection $shopIds): \Illuminate\Database\Eloquent\Collection
    {
        $families = DB::table('product_categories')
            ->whereIn('master_product_category_id', $masterFamilyIds)
            ->whereIn('shop_id', $shopIds)
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereNull('deleted_at')
            ->get(['id', 'shop_id', 'department_id', 'sub_department_id']);

        if ($families->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $collectionIds = DB::table('collection_has_models')
            ->where('model_type', 'ProductCategory')
            ->whereIn('model_id', $families->pluck('id'))
            ->pluck('collection_id');

        $productCategoryIds = DB::table('model_has_collections')
            ->where('model_type', 'ProductCategory')
            ->whereIn('collection_id', $collectionIds)
            ->pluck('model_id')
            ->merge($families->pluck('sub_department_id'))
            ->merge($families->pluck('department_id'))
            ->filter()
            ->unique()
            ->values();

        if ($productCategoryIds->isEmpty() && $collectionIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Webpage::whereIn('shop_id', $families->pluck('shop_id')->unique()->values())
            ->where(function ($query) use ($productCategoryIds, $collectionIds) {
                $query
                    ->where(
                        fn ($listedBy) => $listedBy
                            ->where('model_type', 'ProductCategory')
                            ->whereIn('model_id', $productCategoryIds)
                    )
                    ->orWhere(
                        fn ($listedBy) => $listedBy
                            ->where('model_type', 'Collection')
                            ->whereIn('model_id', $collectionIds)
                    );
            })
            ->get();
    }
}
