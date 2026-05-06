<?php

/*
 * author Louis Perez
 * created on 06-05-2026-11h-27m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisProductsInRecommendation extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(ProductCategory $productCategory, $stockMode = 'all', bool $topSeller = false): LengthAwarePaginator
    {
        $queryBuilder = $this->getBaseQuery($stockMode, $topSeller);
        $queryBuilder
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('webpages')
                    ->whereColumn('webpages.id', 'products.webpage_id')
                    ->where('webpages.state', 'live');
            });

        $queryBuilder
            ->where(function ($query) {
                $query
                    ->whereNull('products.variant_id')
                    ->orWhere('products.is_variant_leader', true);
            });
        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant'),
                DB::raw('exists (
                        select os.is_on_demand
                        from org_stocks os
                        join product_has_org_stocks phos on phos.org_stock_id = os.id
                        where phos.product_id = products.id
                        and os.is_on_demand = true
                    ) as is_on_demand')
            ])
        );
        $perPage = null;
        
        $relatedProduct = $productCategory->relatedProducts()->get();
        $queryBuilder->whereIn('products.id', $relatedProduct->pluck('id'));
        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            $perPage = 250;
        }

        return $this->getData($queryBuilder, $perPage);
    }


    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }


}
