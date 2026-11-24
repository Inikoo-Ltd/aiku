<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Products that are discontinued and hang off the given org stocks, which are about to be set to
 * active: the ones that can safely come back with them (reactivate) and the ones that must stay
 * as they are, each with the reason (skipped). Product state does not follow the org stock state
 * on its own, so this is what an operator confirms before the products are touched.
 *
 * When several candidates in one shop share a code (case-insensitive), the one with the most
 * transactions stays (lowest id on a tie); a code already carried by a live product of the shop
 * leaves none of the candidates.
 */
class GetProductsToReactivateWithOrgStocks
{
    use AsAction;

    /**
     * @param  Collection<int, OrgStock>  $orgStocks
     * @return array{reactivate: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function handle(Collection $orgStocks): array
    {
        $orgStockIds = $orgStocks->pluck('id')->all();

        $productIds = DB::table('product_has_org_stocks')->whereIn('org_stock_id', $orgStockIds)->pluck('product_id')->unique()->all();

        $candidates = Product::whereIn('id', $productIds)
            ->where('state', ProductStateEnum::DISCONTINUED->value)
            ->with(['shop.website', 'webpage'])
            ->orderBy('id')
            ->get();

        $inactiveSiblings = $this->inactiveOrgStockCodes($candidates->pluck('id')->all(), $orgStockIds);
        $transactionCounts = $this->transactionCounts($candidates->pluck('id')->all());

        $reactivate = [];
        $skipped    = [];

        $byShopAndCode = $candidates->groupBy(fn (Product $product) => $product->shop_id.'|'.mb_strtolower($product->code));

        foreach ($byShopAndCode as $group) {
            $winner = $group->sortBy([
                fn (Product $a, Product $b) => ($transactionCounts[$b->id] ?? 0) <=> ($transactionCounts[$a->id] ?? 0),
                fn (Product $a, Product $b) => $a->id <=> $b->id,
            ])->first();

            foreach ($group as $product) {
                $reason = $this->skipReason($product, $inactiveSiblings);

                if (!$reason && $product->id !== $winner->id) {
                    $reason = __('Another product in this shop has the same code');
                }

                if (!$reason && $this->liveProductWithSameCodeExists($product)) {
                    $reason = __('Another product in this shop with the same code is already live');
                }

                $row = $this->row($product, $reason);

                if ($reason) {
                    $skipped[] = $row;
                } else {
                    $reactivate[] = $row;
                }
            }
        }

        $sort = fn (array $a, array $b) => [$a['shop_code'], $a['code'], $a['id']] <=> [$b['shop_code'], $b['code'], $b['id']];
        usort($reactivate, $sort);
        usort($skipped, $sort);

        return ['reactivate' => $reactivate, 'skipped' => $skipped];
    }

    /**
     * @param  array<int, array<int, string>>  $inactiveSiblings
     */
    private function skipReason(Product $product, array $inactiveSiblings): ?string
    {
        if ($product->shop->state !== ShopStateEnum::OPEN) {
            return __('The shop is closed');
        }

        if ($product->shop->type === ShopTypeEnum::EXTERNAL) {
            return __('The shop is an external shop');
        }

        if (!empty($inactiveSiblings[$product->id])) {
            return __('Another SKO of this product is not active: :codes', ['codes' => implode(', ', $inactiveSiblings[$product->id])]);
        }

        if ($product->isExclusive()) {
            return __('The product is private to a customer');
        }

        return null;
    }

    private function liveProductWithSameCodeExists(Product $product): bool
    {
        return Product::where('shop_id', $product->shop_id)
            ->where('id', '!=', $product->id)
            ->whereRaw('lower(code) = ?', [mb_strtolower($product->code)])
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
            ->exists();
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<int, string>>
     */
    private function inactiveOrgStockCodes(array $productIds, array $orgStockIds): array
    {
        if (!$productIds) {
            return [];
        }

        return DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
            ->whereIn('product_has_org_stocks.product_id', $productIds)
            ->whereNotIn('org_stocks.id', $orgStockIds)
            ->where('org_stocks.state', '!=', OrgStockStateEnum::ACTIVE->value)
            ->select('product_has_org_stocks.product_id', 'org_stocks.code')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->pluck('code')->unique()->values()->all())
            ->all();
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    private function transactionCounts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        return DB::table('transactions')
            ->where('model_type', 'Product')
            ->whereIn('model_id', $productIds)
            ->whereNull('deleted_at')
            ->select('model_id', DB::raw('count(*) as total'))
            ->groupBy('model_id')
            ->pluck('total', 'model_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Product $product, ?string $reason): array
    {
        return [
            'id'                   => $product->id,
            'code'                 => $product->code,
            'name'                 => $product->name,
            'shop_code'            => $product->shop->code,
            'has_webpage'          => (bool) $product->webpage,
            'webpage_state'        => $product->webpage?->state?->value,
            'will_create_webpage'  => !$product->webpage && $product->shop->website && !$reason,
            'reason'               => $reason,
        ];
    }
}
