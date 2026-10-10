<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Masters\MasterAsset\GenerateMasterAssetPriceTips;
use App\Actions\Masters\MasterAsset\GetMasterAssetPriceOutlier;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Everything an assistant needs to review the prices of one master family on demand (INI-075).
 * Prices are set a family at a time, so the family is the unit: the facts the nightly price tips
 * collect per product, for every current product of the family, with the family totals next to
 * them. It reads only and gives no opinion; the pricing judgement is the assistant's.
 */
#[Description('Facts to review the pricing of ONE master family, read-only. Give the master shop and the master family code. Master prices are one price per currency used by every shop that follows the master, and staff reprice a family as a whole, so judge the family together and keep its price tiers (sizes, pack sizes) unless the user asks otherwise. Per product: code, name, units per pack, master_prices per currency, price (the base currency in "currency"), cost and margin_pct (cost converted to that currency; null when no cost is recorded; cost_above_price true means the recorded cost is wrong or the price is, so do not build a price on that cost: tell the user to check it), unit_price and unit_price_vs_family_median_pct (against the median unit price of the products listed here, null in a family of fewer than 3 priced products), cover (days of stock, averaged over the organisations by what each sold in the last year; over 365 means the aim is usually clearing stock rather than profit), organisations (per organisation: days_of_cover where 730 means two years or more, stock, sold_last_year, incoming on order, days_out_of_stock per quarter: lost sales, not lost demand), monthly_sales for 24 months (sales in sales_currency, sold in packs), sales (last 12 months) and sales_last_year (the 12 before), new (no sales a year ago), offers running, price_changes (past changes with sales_change_pct: sales in the 3 months after against the 3 before), price_changed_at (set when staff changed the price by hand in the last 30 days: leave it alone unless asked), competitors (confirmed matches only; difference_pct below 0 means they are cheaper per unit), website (product pages online and family page visitors), family (rank and share inside the family) and staff_feedback (earlier suggestions staff turned down and why: do not repeat them). A product with no_stock_linked true comes with its prices only: no stock, cost or sales figures are returned for it, which does not mean it has no sales, and it is left out of the family sales, cover and margin totals (family_totals.products_no_stock_linked names them: tell the user when there are any). family_totals has the sales trend, the spread of unit prices and margins, the products with no sales and those with cost above price. This tool does not change anything.')]
#[IsReadOnly]
class FamilyPricingReviewTool extends Tool
{
    use WithMcpPermissions;

    public const int MAX_PRODUCTS = 150;

    public function handle(Request $request): Response
    {
        $request->validate([
            'master_shop' => ['required', 'string', 'max:64'],
            'family'      => ['required', 'string', 'max:64'],
        ]);

        $group = $request->user()?->group;
        if (!$group || !$this->userCan($request, GroupPermissionsEnum::MASTERS_VIEW->value)) {
            return Response::error('Permission denied.');
        }

        $masterShopCode = strtolower((string) $request->string('master_shop'));
        $masterShop     = MasterShop::where('group_id', $group->id)
            ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [$masterShopCode])->orWhereRaw('lower(code) = ?', [$masterShopCode]))
            ->orderBy('id')
            ->first();
        if (!$masterShop) {
            return Response::error("'{$request->string('master_shop')}' does not match any master shop. Use one of these codes exactly: ".MasterShop::where('group_id', $group->id)->orderBy('id')->pluck('code')->implode(', ').'.');
        }

        $familyCode   = strtolower((string) $request->string('family'));
        $masterFamily = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->whereRaw('lower(code) = ?', [$familyCode])
            ->first();
        if (!$masterFamily) {
            $similar = MasterProductCategory::where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->where(fn ($query) => $query->whereRaw('lower(code) like ?', ["%$familyCode%"])->orWhereRaw('lower(name) like ?', ["%$familyCode%"]))
                ->orderBy('code')
                ->limit(15)
                ->get(['code', 'name'])
                ->map(fn (MasterProductCategory $family) => "$family->code ($family->name)")
                ->implode(', ');

            return Response::error("'{$request->string('family')}' is not a master family code in $masterShop->code.".($similar ? " Closest: $similar." : ''));
        }

        $masterAssets = MasterAsset::where('master_family_id', $masterFamily->id)
            ->where('status', true)
            ->where('is_main', true)
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->orderBy('code')
            ->limit(self::MAX_PRODUCTS + 1)
            ->get(['id', 'code', 'name', 'units', 'master_prices']);

        $currency = GetMasterShopCurrenciesRate::baseCurrencyCode($masterShop->price_exchanges ?? []);
        $signals  = GenerateMasterAssetPriceTips::make()->signals($masterShop, $masterAssets->take(self::MAX_PRODUCTS)->pluck('id')->all());
        $products = $masterAssets->take(self::MAX_PRODUCTS)->map(function (MasterAsset $masterAsset) use ($signals, $currency) {
            $facts = $signals[$masterAsset->id] ?? null;
            $price = (float) data_get($masterAsset->master_prices, "$currency.value", 0);
            $units = (float) $masterAsset->units ?: 1;
            $cost  = $facts['cost'] ?? null;

            return [
                'code'             => $masterAsset->code,
                'name'             => $masterAsset->name,
                'units'            => $units,
                'master_prices'    => collect($masterAsset->master_prices ?? [])->map(fn ($masterPrice) => (float) data_get($masterPrice, 'value', 0))->all(),
                'price'            => $price,
                'unit_price'       => $price > 0 ? round($price / $units, 4) : null,
                'no_stock_linked'  => $facts === null,
                'cost_above_price' => $cost !== null && $price > 0 && $cost >= $price,
                ...collect($facts ?? [])->except(['product', 'family_median_price'])->all(),
            ];
        })->values();

        $unitPrices      = $products->pluck('unit_price')->filter();
        $medianUnitPrice = $unitPrices->count() >= GetMasterAssetPriceOutlier::MINIMUM_SIBLINGS ? (float) $unitPrices->median() : null;
        $products        = $products->map(fn (array $product) => [
            ...$product,
            'unit_price_vs_family_median_pct' => $medianUnitPrice && $product['unit_price'] ? (int) round(100 * ($product['unit_price'] / $medianUnitPrice - 1)) : null,
        ]);

        return Response::json([
            'master_shop'    => $masterShop->code,
            'family'         => ['code' => $masterFamily->code, 'name' => $masterFamily->name],
            'currency'       => $currency,
            'sales_currency' => $group->currency->code,
            'truncated'      => $masterAssets->count() > self::MAX_PRODUCTS,
            'family_totals'  => $this->familyTotals($products),
            'products'       => $products->all(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    private function familyTotals(Collection $products): array
    {
        $sales         = (float) $products->sum(fn (array $product) => $product['sales'] ?? 0);
        $salesLastYear = (float) $products->sum(fn (array $product) => $product['sales_last_year'] ?? 0);
        $costedRight   = $products->filter(fn (array $product) => !$product['cost_above_price'] && ($product['margin_pct'] ?? null) !== null);
        $withCover     = $products->filter(fn (array $product) => ($product['cover'] ?? null) !== null && ($product['sales'] ?? 0) > 0);
        $coverSales    = (float) $withCover->sum('sales');

        return [
            'products'                    => $products->count(),
            'sales'                       => round($sales, 2),
            'sales_last_year'             => round($salesLastYear, 2),
            'sales_change_pct'            => $salesLastYear > 0 ? (int) round(100 * ($sales / $salesLastYear - 1)) : null,
            'cover_weighted_by_sales'     => $coverSales > 0 ? (int) round($withCover->sum(fn (array $product) => $product['cover'] * $product['sales']) / $coverSales) : null,
            'products_over_a_year_cover'  => $products->filter(fn (array $product) => ($product['cover'] ?? 0) > 365)->count(),
            'unit_price'                  => $this->spread($products->pluck('unit_price')),
            'margin_pct'                  => $this->spread($costedRight->pluck('margin_pct')),
            'products_without_sales'      => $products->filter(fn (array $product) => !$product['no_stock_linked'] && ($product['sales'] ?? 0) <= 0)->pluck('code')->values()->all(),
            'products_no_stock_linked'    => $products->where('no_stock_linked', true)->pluck('code')->values()->all(),
            'products_cost_above_price'   => $products->where('cost_above_price', true)->pluck('code')->values()->all(),
            'products_price_changed_by_hand_last_30_days' => $products->filter(fn (array $product) => $product['price_changed_at'] ?? null)->pluck('code')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return array{min: float, median: float, max: float, distinct: int}|null
     */
    private function spread(Collection $values): ?array
    {
        $values = $values->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values();

        if ($values->isEmpty()) {
            return null;
        }

        return [
            'min'      => $values->min(),
            'median'   => round($values->median(), 4),
            'max'      => $values->max(),
            'distinct' => $values->unique()->count(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'master_shop' => $schema->string()->description('Master shop code or slug, e.g. aw')->required(),
            'family'      => $schema->string()->description('Master family code, e.g. EIB')->required(),
        ];
    }
}
