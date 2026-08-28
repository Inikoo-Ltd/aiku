<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait WithPortfolioSKU
{
    public function getSKU(Product|StoredItem $item): ?string
    {
        if (!$item instanceof Product) {
            return $item->reference;
        }

        $skuArray = [];

        foreach ($item->orgStocks as $orgStock) {
            $skuArray[] = $orgStock->stock ? $orgStock->stock->slug : $orgStock->slug;
        }

        return empty($skuArray) ? null : implode('-', $skuArray);
    }

    public function findItemBySKU(string $sku, ?Shop $shop = null, ?string $itemType = null): Product|StoredItem|null
    {
        $sku = trim($sku);

        if (blank($sku)) {
            return null;
        }

        if ($itemType === class_basename(StoredItem::class)) {
            return $this->findStoredItemBySKU($sku, $shop);
        }

        if ($itemType === class_basename(Product::class)) {
            return $this->findProductBySKU($sku, $shop);
        }

        return $this->findProductBySKU($sku, $shop) ?? $this->findStoredItemBySKU($sku, $shop);
    }

    public function findProductBySKU(string $sku, ?Shop $shop = null): ?Product
    {
        return $this->findProductsBySKU($sku, $shop)->first();
    }

    public function findProductsBySKU(string $sku, ?Shop $shop = null): EloquentCollection
    {
        $candidateProductIds = $this->getCandidateProductIds($sku);

        if (blank($candidateProductIds)) {
            return Product::whereRaw('1 = 0')->get();
        }

        $slugsByProduct = $this->getSlugsByProduct($candidateProductIds);

        $matchedProductIds = $candidateProductIds->filter(
            fn ($productId) => $this->skuIsMadeOfSlugs($sku, $slugsByProduct->get($productId, []))
        );

        if (blank($matchedProductIds)) {
            return Product::whereRaw('1 = 0')->get();
        }

        return Product::whereIn('id', $matchedProductIds)
            ->when($shop, fn ($query) => $query->where('shop_id', $shop->id))
            ->orderByRaw("case products.state when ? then 0 when ? then 1 when ? then 2 else 3 end", [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::IN_PROCESS->value,
                ProductStateEnum::DISCONTINUING->value
            ])
            ->orderBy('products.id')
            ->get();
    }

    public function findStoredItemBySKU(string $sku, ?Shop $shop = null): ?StoredItem
    {
        return StoredItem::where('reference', $sku)
            ->when(
                $shop,
                fn ($query) => $query->whereHas('fulfilment', fn ($query) => $query->where('shop_id', $shop->id))
            )
            ->first();
    }

    private function getCandidateProductIds(string $sku): Collection
    {
        $words           = explode('-', $sku);
        $leadingSlugs    = [];
        $leadingSlug     = '';

        foreach ($words as $word) {
            $leadingSlug    = $leadingSlug === '' ? $word : $leadingSlug.'-'.$word;
            $leadingSlugs[] = $leadingSlug;
        }

        $orgStockIds = DB::table('org_stocks')
            ->leftJoin('stocks', 'stocks.id', '=', 'org_stocks.stock_id')
            ->where(function ($query) use ($leadingSlugs) {
                $query->whereIn('stocks.slug', $leadingSlugs)
                    ->orWhere(function ($query) use ($leadingSlugs) {
                        $query->whereNull('org_stocks.stock_id')
                            ->whereIn('org_stocks.slug', $leadingSlugs);
                    });
            })
            ->pluck('org_stocks.id');

        if (blank($orgStockIds)) {
            return collect();
        }

        return DB::table('product_has_org_stocks')
            ->whereIn('org_stock_id', $orgStockIds)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, array<int, string>>
     */
    private function getSlugsByProduct(Collection $productIds): Collection
    {
        return DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
            ->leftJoin('stocks', 'stocks.id', '=', 'org_stocks.stock_id')
            ->whereIn('product_has_org_stocks.product_id', $productIds)
            ->selectRaw('product_has_org_stocks.product_id, coalesce(stocks.slug, org_stocks.slug) as slug')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('slug')->filter()->values()->all());
    }

    private function skuIsMadeOfSlugs(string $sku, array $slugs): bool
    {
        if (blank($slugs)) {
            return false;
        }

        if (count($slugs) === 1) {
            return $sku === $slugs[0];
        }

        foreach ($slugs as $index => $slug) {
            if (!str_starts_with($sku, $slug.'-')) {
                continue;
            }

            $remainingSlugs = $slugs;
            unset($remainingSlugs[$index]);

            if ($this->skuIsMadeOfSlugs(substr($sku, strlen($slug) + 1), array_values($remainingSlugs))) {
                return true;
            }
        }

        return false;
    }
}
