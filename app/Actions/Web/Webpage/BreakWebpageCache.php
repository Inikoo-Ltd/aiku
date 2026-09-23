<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 13:03:07 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakWebpageCache extends OrgAction implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(?Webpage $webpage, bool $includeChildren = false): string
    {
        $slug = 'empty';
        if ($webpage) {
            $slug = $webpage->slug;
        }

        return $slug.'-'.$includeChildren ?? 'i';
    }

    public function handle(?Webpage $webpage, bool $includeChildren = false): void
    {
        if (!$webpage) {
            return;
        }

        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
        Cache::forget($key);
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
        Cache::forget($key);

        BanVarnishWebpage::run($webpage);
        PurgeVarnishWebpageUrl::run($webpage);

        if ($webpage->model instanceof ProductCategory) {
            /** @var ProductCategory $productCategory */
            $productCategory = $webpage->model;

            if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
                $this->breakFamilyListingsCache($productCategory);
            }

            if ($includeChildren) {
                foreach ($productCategory->getProducts() as $product) {
                    BreakWebpageCache::dispatch($product->webpage, false);
                }
            }
        }
    }

    /**
     * The family lists on department, sub-department and collection pages are built while those
     * pages render and then cached whole for hours, so a family that has just gone live - or been
     * renamed, closed, moved or deleted - only reaches them once their own cache is dropped too.
     */
    public function breakFamilyListingsCache(ProductCategory $family): void
    {
        foreach ($this->getFamilyListingWebpages($family) as $listingWebpage) {
            BreakWebpageCache::dispatch($listingWebpage, false);
        }
    }

    /**
     * Every webpage of a product category, not just the one it points at: a department also has a
     * families-overview page, and both list its families.
     */
    public function breakProductCategoryWebpagesCache(?ProductCategory $productCategory): void
    {
        if (!$productCategory) {
            return;
        }

        foreach ($productCategory->webpages as $webpage) {
            BreakWebpageCache::dispatch($webpage, false);
        }
    }

    /**
     * @return EloquentCollection<int, Webpage>
     */
    public function getFamilyListingWebpages(ProductCategory $family): EloquentCollection
    {
        $collectionIds = DB::table('collection_has_models')
            ->where('model_type', 'ProductCategory')
            ->where('model_id', $family->id)
            ->pluck('collection_id');

        $productCategoryIds = DB::table('model_has_collections')
            ->where('model_type', 'ProductCategory')
            ->whereIn('collection_id', $collectionIds)
            ->pluck('model_id')
            ->merge([$family->sub_department_id, $family->department_id])
            ->filter()
            ->unique()
            ->values();

        if ($productCategoryIds->isEmpty() && $collectionIds->isEmpty()) {
            return new EloquentCollection();
        }

        return Webpage::where('shop_id', $family->shop_id)
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

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisation($webpage->organisation, $request);

        $this->handle($webpage);
    }
}
