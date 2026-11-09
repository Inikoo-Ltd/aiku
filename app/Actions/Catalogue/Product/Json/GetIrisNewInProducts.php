<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedSort;

class GetIrisNewInProducts extends IrisAction
{
    use WithIrisProductsInWebpage;

    /**
     * The shop products still inside their NEW IN period. first_available_at is stamped once per product,
     * and a product belongs to one shop, so each website counts its own arrivals and a restock, an edit or
     * selling out never restarts the period. "New arrivals", the sort the list opens with, orders them by it.
     */
    public function handle(Shop $shop, string $stockMode = 'all'): LengthAwarePaginator
    {
        $queryBuilder = $this->getBaseQuery($stockMode);

        $queryBuilder->where('products.shop_id', $shop->id)
            ->where('products.first_available_at', '>', ProductBadgeEnum::newInSince())
            ->where(function ($query) {
                $query->where('products.is_minion_variant', false)
                    ->orWhere('products.is_variant_leader', true);
            });

        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant'),
            ])
        );
        $queryBuilder->selectRaw('\''.request()->path().'\' as parent_url');

        $sorts = array_values(array_diff($this->getAllowedSorts(), ['created_at']));

        return $queryBuilder
            ->defaultSort(AllowedSort::field('-created_at', 'products.first_available_at'))
            ->allowedSorts([...$sorts, AllowedSort::field('created_at', 'products.first_available_at')])
            ->allowedFilters($this->getAllowedFilters())
            ->withIrisPaginator(50)
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->shop);
    }
}
