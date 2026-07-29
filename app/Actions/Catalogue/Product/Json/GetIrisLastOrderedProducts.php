<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-10h-47m
 * GitHub: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Http\Resources\Catalogue\LastOrderedProductsResource;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Logic is built in three steps:
 *
 * 1. Show products in the same Family. One product can only appear once per date. Only products that 'for sale' and 'live webpage'.
 *
 * 2. Borrow from the other shops of the same master shop, but only when this shop cannot fill the
 *    10 list with purchases of the last 30 days. A product that never sold here, or that has not
 *    sold here for the last 30 days, will use the dates of the other shops. When that still cannot
 *    fill the list, borrow from every shop in the group, across master shops, matching the same
 *    physical product by its trade unit.
 *
 * 3. Displaying 10 list, max 3 can share the same date and one product can appear max 4 times.
 *    Prioritize unique products rather than repeated ones. The repeated products must be a purchase
 *    of the last 60 days. When the dates run out, allow one more card per date.
 */
class GetIrisLastOrderedProducts extends IrisAction
{
    private const SLOTS = 10;

    private const MAX_PER_DAY = 3;

    private const MAX_PER_PRODUCT = 4;

    private const REPEAT_WITHIN_DAYS = 60;

    private const CANDIDATE_POOL = 100;

    private const MASTER_SHOP_LOOKBACK_DAYS = 90;

    private const STALE_AFTER_DAYS = 30;

    public function handle(ProductCategory $productCategory, array $modelData): Collection
    {
        $ignoredProductId = Arr::get($modelData, 'ignoredProductId');
        $cacheKey         = sprintf(
            'iris:last_ordered_products:%s:%s',
            $productCategory->id,
            $ignoredProductId ?? 'none'
        );

        $cachedProducts = Cache::get($cacheKey);
        if ($cachedProducts !== null) {
            return $cachedProducts;
        }

        $candidates = $this->getShopCandidates($productCategory, $ignoredProductId);

        if ($productCategory->master_product_category_id && $this->needsMasterShopBoost($candidates)) {
            $candidates = $this->mergeMasterShopCandidates(
                $candidates,
                $this->getMasterShopCandidates($productCategory, $ignoredProductId)
            );

            if ($this->needsMasterShopBoost($candidates)) {
                $candidates = $this->mergeMasterShopCandidates(
                    $candidates,
                    $this->getGroupCandidates($productCategory, $ignoredProductId)
                );
            }
        }

        $products = $this->spreadOverDays($candidates);

        Cache::put($cacheKey, $products, $this->cacheTtl($products->count()));

        return $products;
    }

    /** What this shop sold from this family, one purchase per product per date. */
    private function getShopCandidates(ProductCategory $productCategory, ?string $ignoredProductId): Collection
    {
        $query = DB::table('transactions')
            ->selectRaw('DISTINCT ON (products.id, transactions.date::date) products.id as product_id, products.code, products.name, products.web_images, webpages.canonical_url, transactions.date as submitted_at')
            ->join('products', 'transactions.model_id', '=', 'products.id')
            ->join('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->where('transactions.model_type', 'Product')
            ->where('transactions.family_id', $productCategory->id)
            ->whereNotIn('transactions.state', [TransactionStateEnum::CREATING, TransactionStateEnum::CANCELLED])
            ->orderByRaw('products.id, transactions.date::date DESC, transactions.date DESC');

        return $this->fetchCandidates($query, $ignoredProductId);
    }

    /** What the other shops sold of the same products, put back on this shop's products. */
    private function getMasterShopCandidates(ProductCategory $productCategory, ?string $ignoredProductId): Collection
    {
        $query = DB::table('invoice_transactions')
            ->selectRaw('DISTINCT ON (products.id, invoice_transactions.date::date) products.id as product_id, products.code, products.name, products.web_images, webpages.canonical_url, invoice_transactions.date as submitted_at')
            ->join('products', function ($join) use ($productCategory) {
                $join->on('products.master_product_id', '=', 'invoice_transactions.master_asset_id')
                    ->where('products.shop_id', $productCategory->shop_id);
            })
            ->join('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->where('invoice_transactions.model_type', 'Product')
            ->where('invoice_transactions.master_family_id', $productCategory->master_product_category_id)
            ->where('invoice_transactions.date', '>', now()->subDays(self::MASTER_SHOP_LOOKBACK_DAYS))
            ->where(function ($query) {
                $query->where('invoice_transactions.is_refund', false)
                    ->orWhereNull('invoice_transactions.is_refund');
            })
            ->orderByRaw('products.id, invoice_transactions.date::date DESC, invoice_transactions.date DESC');

        return $this->fetchCandidates($query, $ignoredProductId);
    }

    /** What every shop in the group sold of the same trade unit, put back on this shop's products. */
    private function getGroupCandidates(ProductCategory $productCategory, ?string $ignoredProductId): Collection
    {
        $query = DB::table('invoice_transactions')
            ->selectRaw('DISTINCT ON (products.id, invoice_transactions.date::date) products.id as product_id, products.code, products.name, products.web_images, webpages.canonical_url, invoice_transactions.date as submitted_at')
            ->join('model_has_trade_units as seller_trade_units', function ($join) {
                $join->on('seller_trade_units.model_id', '=', 'invoice_transactions.master_asset_id')
                    ->where('seller_trade_units.model_type', 'MasterAsset');
            })
            ->join('model_has_trade_units as product_trade_units', function ($join) {
                $join->on('product_trade_units.trade_unit_id', '=', 'seller_trade_units.trade_unit_id')
                    ->where('product_trade_units.model_type', 'Product');
            })
            ->join('products', function ($join) use ($productCategory) {
                $join->on('products.id', '=', 'product_trade_units.model_id')
                    ->where('products.shop_id', $productCategory->shop_id)
                    ->where('products.family_id', $productCategory->id);
            })
            ->join('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->where('invoice_transactions.model_type', 'Product')
            ->where('invoice_transactions.date', '>', now()->subDays(self::MASTER_SHOP_LOOKBACK_DAYS))
            ->where(function ($query) use ($productCategory) {
                $query->where('invoice_transactions.master_family_id', '!=', $productCategory->master_product_category_id)
                    ->orWhereNull('invoice_transactions.master_family_id');
            })
            ->where(function ($query) {
                $query->where('invoice_transactions.is_refund', false)
                    ->orWhereNull('invoice_transactions.is_refund');
            })
            ->orderByRaw('products.id, invoice_transactions.date::date DESC, invoice_transactions.date DESC');

        return $this->fetchCandidates($query, $ignoredProductId);
    }

    /** Numbers the purchases of each product, and keeps a few purchases of each recent date. */
    private function fetchCandidates(Builder $query, ?string $ignoredProductId): Collection
    {
        if ($ignoredProductId) {
            $query->where('products.id', '!=', $ignoredProductId);
        }

        $query->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->where('products.is_for_sale', true);

        $rankedPurchases = DB::query()
            ->fromSub($query, 'daily_purchases')
            ->selectRaw('*, row_number() over (partition by product_id order by submitted_at desc) as product_rank, dense_rank() over (order by submitted_at::date desc) as day_rank');

        $purchasesOfRecentDays = DB::query()
            ->fromSub($rankedPurchases, 'ranked_purchases')
            ->selectRaw('*, row_number() over (partition by submitted_at::date order by product_rank, submitted_at desc) as within_day_rank')
            ->where('product_rank', '<=', self::MAX_PER_PRODUCT)
            ->where('day_rank', '<=', self::SLOTS);

        return DB::query()
            ->fromSub($purchasesOfRecentDays, 'purchases_of_recent_days')
            ->where('within_day_rank', '<=', self::SLOTS)
            ->orderByDesc('submitted_at')
            ->limit(self::CANDIDATE_POOL)
            ->get();
    }

    /** Is this shop too quiet to fill the 10 list on its own? */
    private function needsMasterShopBoost(Collection $shopCandidates): bool
    {
        $staleThreshold = now()->subDays(self::STALE_AFTER_DAYS);

        $recentCandidates = $shopCandidates
            ->take(self::SLOTS)
            ->filter(fn ($candidate) => Carbon::parse($candidate->submitted_at)->gte($staleThreshold));

        return $recentCandidates->count() < self::SLOTS;
    }

    /** Keeps the dates of products still selling here, uses the other shops' dates for the rest. */
    private function mergeMasterShopCandidates(Collection $shopCandidates, Collection $masterShopCandidates): Collection
    {
        $staleThreshold = now()->subDays(self::STALE_AFTER_DAYS);

        $recentlySoldProductIds = $shopCandidates
            ->groupBy('product_id')
            ->filter(fn (Collection $purchases) => Carbon::parse($purchases->first()->submitted_at)->gte($staleThreshold))
            ->keys()
            ->all();

        $productIdsInMasterShop = $masterShopCandidates->pluck('product_id')->unique()->all();

        $keptShopCandidates = $shopCandidates->filter(
            fn ($candidate) => in_array($candidate->product_id, $recentlySoldProductIds)
                || !in_array($candidate->product_id, $productIdsInMasterShop)
        );

        return $keptShopCandidates
            ->concat($masterShopCandidates->whereNotIn('product_id', $recentlySoldProductIds))
            ->sortByDesc('submitted_at')
            ->values();
    }

    /** Picks the 10, and only loosens the 3 per date rule when there are not enough dates. */
    private function spreadOverDays(Collection $candidates): Collection
    {
        $maxPerDay     = self::MAX_PER_DAY;
        $previousCount = -1;

        do {
            $selected = $this->takeWithDailyQuota($candidates, $maxPerDay);
            $relaxed  = $selected->count() > $previousCount;

            $previousCount = $selected->count();
            $maxPerDay++;
        } while ($selected->count() < self::SLOTS && $relaxed);

        return $selected->sortByDesc('submitted_at')->values();
    }

    /** Display unique products first, then let repeated product to show */
    private function takeWithDailyQuota(Collection $candidates, int $maxPerDay): Collection
    {
        $repeatThreshold = now()->subDays(self::REPEAT_WITHIN_DAYS);
        $selected        = collect();
        $perDay          = [];

        for ($productRank = 1; $productRank <= self::MAX_PER_PRODUCT; $productRank++) {
            foreach ($candidates->where('product_rank', $productRank) as $candidate) {
                if ($selected->count() >= self::SLOTS) {
                    return $selected;
                }

                if ($productRank > 1 && Carbon::parse($candidate->submitted_at)->lt($repeatThreshold)) {
                    continue;
                }

                $day = substr((string) $candidate->submitted_at, 0, 10);
                if (Arr::get($perDay, $day, 0) >= $maxPerDay) {
                    continue;
                }

                $perDay[$day] = Arr::get($perDay, $day, 0) + 1;
                $selected->push($candidate);
            }
        }

        return $selected;
    }

    public function cacheTtl(int $itemsInQuery): \DateTimeInterface
    {
        if ($itemsInQuery < 4) {
            return now()->addMinutes(30);
        }

        if ($itemsInQuery < 10) {
            return now()->addHours(6);
        }

        return now()->addDay();
    }

    public function rules(): array
    {
        return [
            'ignoredProductId' => ['sometimes', 'string'],
        ];
    }

    public function jsonResponse($products): AnonymousResourceCollection
    {
        return LastOrderedProductsResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): Collection
    {
        $this->initialisation($request);

        return $this->handle($productCategory, $this->validatedData);
    }

}
