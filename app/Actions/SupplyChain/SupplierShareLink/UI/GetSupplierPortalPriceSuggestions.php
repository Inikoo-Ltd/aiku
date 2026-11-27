<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-25 10:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Masters\Competitor\GetConfirmedCompetitorPrices;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The lower price the system suggests to the supplier for a product, never a higher one, with the reasons the supplier
 * reads: its sales over the last 12 months fell against the 12 months before, or a competitor sells the same item
 * cheaper than us at its regular price. A sales drop takes a third of the drop off the price, up to 15%; a cheaper
 * competitor takes the gap off, so our margin holds at their price; the larger wins, up to 30%. A product whose price
 * changed in the last 6 months gets none, so sales have time to answer the new price.
 */
class GetSupplierPortalPriceSuggestions
{
    use AsObject;

    public const float SALES_DROP_FROM = 0.2;

    public const float CUT_PER_SALES_DROP = 1 / 3;

    public const float SALES_CUT_UP_TO = 0.15;

    public const float COMPETITOR_GAP_FROM = 0.05;

    public const float CUT_UP_TO = 0.3;

    public const int UNITS_A_YEAR_EARLIER_FROM = 10;

    public const int MONTHS_SINCE_LAST_CHANGE = 6;

    /**
     * @return array<int, array{price: float, change_percent: int, sales: array{sold: float, last_year: float, change_percent: int}|null, competitor: array{gap_percent: int, on_sale: bool}|null}>
     */
    public function handle(Supplier $supplier): array
    {
        $products = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', SupplierProductStateEnum::ACTIVE)
            ->where('cost', '>', 0)
            ->whereNotIn('id', $this->recentlyChanged($supplier))
            ->pluck('cost', 'id');

        if ($products->isEmpty()) {
            return [];
        }

        $sales       = $this->sales($supplier);
        $competitors = $this->cheapestCompetitors($supplier, $products->keys()->all());

        return $products
            ->map(fn (string $cost, int $id) => $this->suggestion((float) $cost, $sales->get($id), $competitors[$id] ?? null))
            ->filter()
            ->all();
    }

    /**
     * @param  array{gap: float, on_sale: bool}|null  $competitor
     * @return array{price: float, change_percent: int, sales: array{sold: float, last_year: float, change_percent: int}|null, competitor: array{gap_percent: int, on_sale: bool}|null}|null
     */
    private function suggestion(float $cost, ?object $sales, ?array $competitor): ?array
    {
        $soldBefore = (float) ($sales->last_year ?? 0);
        $salesDrop  = $soldBefore >= $cost * self::UNITS_A_YEAR_EARLIER_FROM ? 1 - (float) $sales->sold / $soldBefore : 0;
        $salesCut   = $salesDrop >= self::SALES_DROP_FROM ? min(self::SALES_CUT_UP_TO, $salesDrop * self::CUT_PER_SALES_DROP) : 0;

        $competitorGap = $competitor['gap'] ?? 0;
        $competitorCut = -$competitorGap >= self::COMPETITOR_GAP_FROM ? min(self::CUT_UP_TO, -$competitorGap) : 0;

        $price = $this->rounded($cost * (1 - min(self::CUT_UP_TO, max($salesCut, $competitorCut))), $cost);
        if ($price <= 0 || $price >= $cost) {
            return null;
        }

        return [
            'price'          => $price,
            'change_percent' => (int) round(100 * ($price / $cost - 1)),
            'sales'          => $salesCut > 0 ? [
                'sold'           => round((float) $sales->sold, 2),
                'last_year'      => round($soldBefore, 2),
                'change_percent' => (int) round(-100 * $salesDrop),
            ] : null,
            'competitor'     => $competitorCut > 0 ? [
                'gap_percent' => (int) round(100 * $competitorGap),
                'on_sale'     => $competitor['on_sale'],
            ] : null,
        ];
    }

    private function rounded(float $price, float $cost): float
    {
        $step = max($cost >= 2 ? 0.05 : 0.01, 5 * 10 ** (floor(log10($price)) - 3));

        return round(round($price / $step) * $step, 2);
    }

    /**
     * @return Collection<int, object{sold: string, last_year: string}>
     */
    private function sales(Supplier $supplier): Collection
    {
        $products = GetAgentSalesProducts::make()->valuedBy(null, null, [])->atSupplierPrices($supplier);

        return $products->products($supplier, null, false, DateIntervalEnum::ONE_YEAR)->get()->keyBy('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function recentlyChanged(Supplier $supplier): Collection
    {
        return SupplierSubmission::where('supplier_id', $supplier->id)
            ->where('type', SupplierSubmissionTypeEnum::PRICE_CHANGE)
            ->where('submitted_at', '>=', now()->subMonths(self::MONTHS_SINCE_LAST_CHANGE))
            ->whereIn('state', [SupplierSubmissionStateEnum::RECEIVED, SupplierSubmissionStateEnum::UNDER_REVIEW, SupplierSubmissionStateEnum::MORE_INFORMATION_REQUESTED, SupplierSubmissionStateEnum::ACCEPTED])
            ->whereNotNull('supplier_product_id')
            ->pluck('supplier_product_id');
    }

    /**
     * How far below our price per unit the cheapest confirmed same-item competitor sells, at its regular price: a
     * price on sale counts at the price it was before, because a sale ends and a supplier's price does not.
     *
     * @param  array<int, int>  $supplierProductIds
     * @return array<int, array{gap: float, on_sale: bool}>
     */
    private function cheapestCompetitors(Supplier $supplier, array $supplierProductIds): array
    {
        $masterAssets = DB::table('org_supplier_products as osp')
            ->join('org_stock_has_org_supplier_products as link', fn ($join) => $join->on('link.org_supplier_product_id', 'osp.id')->where('link.status', true))
            ->join('product_has_org_stocks as product_stock', 'product_stock.org_stock_id', 'link.org_stock_id')
            ->join('products', 'products.id', 'product_stock.product_id')
            ->whereIn('osp.supplier_product_id', $supplierProductIds)
            ->whereNotNull('products.master_product_id')
            ->distinct()
            ->get(['osp.supplier_product_id', 'products.master_product_id']);

        if ($masterAssets->isEmpty()) {
            return [];
        }

        $exchanges = [];
        $gaps      = MasterAssetCompetitorProduct::whereIn('master_asset_id', $masterAssets->pluck('master_product_id')->unique()->all())
            ->where('status', MasterAssetCompetitorProductStatusEnum::CONFIRMED)
            ->where('is_same_item', true)
            ->with(['masterAsset:id,price,rrp,units,master_prices,master_rrps', 'competitorProduct.competitor.currency'])
            ->get()
            ->map(function (MasterAssetCompetitorProduct $match) use ($supplier, &$exchanges) {
                $competitorProduct = $match->competitorProduct;
                $price             = $competitorProduct->price === null ? null : (float) $competitorProduct->price;
                $wasPrice          = (float) $competitorProduct->was_price;
                $isOnSale          = $price !== null && $wasPrice > $price;

                [$ours, $theirs] = GetConfirmedCompetitorPrices::unitPrices(
                    $match->masterAsset,
                    $competitorProduct->competitor,
                    $isOnSale ? $wasPrice : $price,
                    (float) ($match->competitor_units ?? $competitorProduct->units),
                    $supplier->group->currency,
                    $exchanges,
                    $match->units_verified
                );

                if (!$ours || !$theirs || $theirs / $ours < GetConfirmedCompetitorPrices::BELIEVABLE_FROM || $theirs / $ours > GetConfirmedCompetitorPrices::BELIEVABLE_TO) {
                    return null;
                }

                return ['master_asset_id' => $match->master_asset_id, 'gap' => $theirs / $ours - 1, 'on_sale' => $isOnSale];
            })
            ->filter()
            ->groupBy('master_asset_id');

        return $masterAssets
            ->groupBy('supplier_product_id')
            ->map(fn (Collection $rows) => $rows->flatMap(fn ($row) => $gaps->get($row->master_product_id, []))->sortBy('gap')->first())
            ->filter()
            ->map(fn (array $cheapest) => ['gap' => $cheapest['gap'], 'on_sale' => $cheapest['on_sale']])
            ->all();
    }
}
