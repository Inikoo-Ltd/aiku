<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The single source of truth for what counts as a master↔product anomaly:
 * trade unit composition, warehouse picking and prices.
 *
 * A product that should follow the master but does not is an anomaly. A product
 * flagged not_follow_master_* is a rebel, and is reported whether or not its
 * values currently differ: the flag alone means future master updates will not
 * reach it, which is what the yellow block exists to surface.
 *
 * @return array<int, array{product_id: int, shop_code: string, shop_slug: string, url: string, issues: list<string>, ignored_issues: list<string>, ignored_scopes: list<string>}> keyed by product id
 */
class GetMasterAssetAnomalies
{
    use AsAction;

    public function handle(MasterAsset $masterProduct): array
    {
        $masterTradeUnits = $masterProduct->tradeUnits->pluck('pivot.quantity', 'id');
        $masterStocks     = $masterProduct->stocks->pluck('pivot.quantity', 'id');

        $anomalies = [];
        /*
         * Discontinued products are excluded: nobody sells them, they outnumber the real
         * problem by an order of magnitude (16k of 19.5k flagged on aw), and a red block
         * driven by them sends staff after products that no longer exist.
         */
        $products = $masterProduct->products()
            ->where('products.status', '!=', ProductStatusEnum::DISCONTINUED)
            ->with(['shop', 'family', 'organisation', 'currency', 'tradeUnits', 'orgStocks.tradeUnits'])
            ->get();

        foreach ($products as $product) {
            $composition = $this->compositionDeviations($masterProduct, $masterTradeUnits, $masterStocks, $product);
            $pricing     = $this->pricingDeviations($masterProduct, $product);

            $issues        = [];
            $ignoredIssues = [];
            $ignoredScopes = [];

            if ($product->not_follow_master_trade_units) {
                $ignoredScopes[] = 'trade_units';
                $ignoredIssues   = array_merge($ignoredIssues, $composition ?: [__('Not following master composition and picking (currently identical to master)')]);
            } else {
                $issues = array_merge($issues, $composition);
            }

            /*
             * A shop with follow_master_pricing off, or a family that opted out, is skipped
             * by the price cascade exactly like a product level opt-out. Calling those an
             * anomaly would promise a fix the raygun can never deliver, so they are reported
             * as rebellions — but not as ones a product level kill can end.
             */
            $shopFollowsPrices   = data_get($product->shop->settings, 'catalog.follow_master_pricing', true);
            $familyFollowsPrices = !$product->family?->not_follow_master_prices;

            if ($product->not_follow_master_prices) {
                $ignoredScopes[] = 'prices';
                $ignoredIssues   = array_merge($ignoredIssues, $pricing ?: [__('Not following master price and RRP (currently identical to master)')]);
            } elseif (!$shopFollowsPrices) {
                $ignoredIssues = array_merge($ignoredIssues, $pricing ?: [__('Shop is set not to follow master pricing (currently identical to master)')]);
            } elseif (!$familyFollowsPrices) {
                $ignoredIssues = array_merge($ignoredIssues, $pricing ?: [__('Family is set not to follow master pricing (currently identical to master)')]);
            } else {
                $issues = array_merge($issues, $pricing);
            }

            if ($issues || $ignoredIssues) {
                $anomalies[$product->id] = [
                    'product_id'     => $product->id,
                    'shop_code'      => $product->shop->code,
                    'shop_slug'      => $product->shop->slug,
                    'url'            => route('grp.org.shops.show.catalogue.products.all_products.show', [
                        'organisation' => $product->organisation->slug,
                        'shop'         => $product->shop->slug,
                        'product'      => $product->slug,
                    ]),
                    'issues'         => $issues,
                    'ignored_issues' => $ignoredIssues,
                    'ignored_scopes' => $ignoredScopes,
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Whether a single product's trade units and warehouse picking already match its
     * master, without building the whole master's anomaly report around it.
     */
    public function productFollowsComposition(MasterAsset $masterProduct, Product $product): bool
    {
        return $this->compositionDeviations(
            $masterProduct,
            $masterProduct->tradeUnits->pluck('pivot.quantity', 'id'),
            $masterProduct->stocks->pluck('pivot.quantity', 'id'),
            $product
        ) === [];
    }

    /**
     * @return list<string>
     */
    private function compositionDeviations(MasterAsset $masterProduct, Collection $masterTradeUnits, Collection $masterStocks, Product $product): array
    {
        $deviations = [];

        $productTradeUnits = $product->tradeUnits->pluck('pivot.quantity', 'id');
        if ($this->quantitiesDiffer($masterTradeUnits, $productTradeUnits)) {
            /*
             * Codes, not bare quantities: a product can hold the same quantities of
             * different trade units, and "2+2+2 vs 2+2+2" tells the reader nothing.
             */
            $codes = $masterProduct->tradeUnits->pluck('code', 'id')
                ->union($product->tradeUnits->pluck('code', 'id'));

            $deviations[] = __('Trade unit composition differs from master (:product vs :master)', [
                'product' => $this->describeQuantities($productTradeUnits, $codes),
                'master'  => $this->describeQuantities($masterTradeUnits, $codes),
            ]);
        }

        $productStocks  = $product->orgStocks->pluck('pivot.quantity', 'stock_id');
        $expectedStocks = $this->expectedStockPicks($masterProduct, $masterStocks, $product);
        if ($this->quantitiesDiffer($expectedStocks, $productStocks)) {
            $codes = $masterProduct->stocks->pluck('code', 'id')
                ->union($product->orgStocks->pluck('code', 'stock_id'));

            $deviations[] = __('Warehouse picking differs from master (picks :product, master says :master)', [
                'product' => $this->describeQuantities($productStocks, $codes),
                'master'  => $this->describeQuantities($expectedStocks, $codes),
            ]);
        }

        return $deviations;
    }

    /**
     * @return list<string>
     */
    private function pricingDeviations(MasterAsset $masterProduct, Product $product): array
    {
        $deviations   = [];
        $currencyCode = $product->currency->code;
        $masterPrice  = Arr::get($masterProduct->master_prices, "$currencyCode.value");
        $masterRrp    = Arr::get($masterProduct->master_rrps, "$currencyCode.value");

        if ($masterPrice !== null && abs((float)$product->price - (float)$masterPrice) > 0.005) {
            $deviations[] = __('Price differs from master (:product vs :master :currency)', [
                'product'  => $product->price,
                'master'   => $masterPrice,
                'currency' => $currencyCode,
            ]);
        }
        if ($masterRrp !== null && $product->rrp !== null && abs((float)$product->rrp - (float)$masterRrp) > 0.005) {
            $deviations[] = __('RRP differs from master (:product vs :master :currency)', [
                'product'  => $product->rrp,
                'master'   => $masterRrp,
                'currency' => $currencyCode,
            ]);
        }

        return $deviations;
    }

    /**
     * What SyncProductOrgStocksFromTradeUnits would write for this product: master units
     * divided by the organisation's own packing (OS-TU pivot), falling back to the group
     * stock's packing. Comparing against the raw master stock quantity flags every
     * warehouse whose packing differs from the group default as a false anomaly.
     */
    private function expectedStockPicks(MasterAsset $masterProduct, Collection $masterStocks, Product $product): Collection
    {
        $expected = collect($masterStocks);
        foreach ($masterProduct->tradeUnits as $tradeUnit) {
            foreach ($tradeUnit->stocks as $stock) {
                $orgStock  = $product->orgStocks->firstWhere('stock_id', $stock->id);
                $orgPacked = $orgStock?->tradeUnits->firstWhere('id', $tradeUnit->id)?->pivot->quantity
                    ?? $stock->pivot->quantity;
                $expected->put($stock->id, $tradeUnit->pivot->quantity / ($orgPacked ?: 1));
            }
        }

        return $expected;
    }

    private function quantitiesDiffer(Collection $master, Collection $product): bool
    {
        if ($master->keys()->sort()->values()->all() !== $product->keys()->sort()->values()->all()) {
            return true;
        }

        foreach ($master as $id => $quantity) {
            if (abs((float)$quantity - (float)$product->get($id)) > 0.0000001) {
                return true;
            }
        }

        return false;
    }

    private function describeQuantities(Collection $quantities, ?Collection $codes = null): string
    {
        if ($quantities->isEmpty()) {
            return __('none');
        }

        return $quantities->map(function ($quantity, $id) use ($codes) {
            $amount = rtrim(rtrim(number_format((float)$quantity, 4, '.', ''), '0'), '.');
            $code   = $codes?->get($id);

            return $code ? $amount.'×'.$code : $amount;
        })->implode(' + ');
    }
}
