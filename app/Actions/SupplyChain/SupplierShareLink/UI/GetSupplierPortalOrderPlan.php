<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Helpers\Tag;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What we expect to order from the supplier in the next three months, so it can buy its raw materials in time. Read
 * from each organisation's sales forecast for the SKOs the supplier's products feed: the day the stock we hold and
 * what is already coming runs out, less the lead time, is when we expect to order, and what we would sell in the
 * three months plus the lead time, less that stock, is how much. Our stock levels, sales and margins stay out: the
 * supplier sees only when, how much of its own products, and at its own prices. A SKO bought from several suppliers
 * gives each an even share, the same split as the sales figures.
 */
class GetSupplierPortalOrderPlan
{
    use AsObject;

    public const int HORIZON_DAYS = 90;

    /**
     * @return array{as_of: string|null, currency: string|null, horizon_days: int, months: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public function handle(Supplier $supplier): array
    {
        $today  = now()->startOfDay();
        $months = collect(range(0, (int) $today->copy()->startOfMonth()->diffInMonths($today->copy()->addDays(self::HORIZON_DAYS)->startOfMonth())))
            ->map(fn (int $offset) => $today->copy()->startOfMonth()->addMonthsNoOverflow($offset));

        $rows = $supplier->orgSuppliers()->with('organisation')->get()
            ->flatMap(fn (OrgSupplier $orgSupplier) => $this->rows($orgSupplier, $today));

        $products = $this->byProduct($rows, $months);

        return [
            'as_of'        => $rows->max('forecast_at') ? Carbon::parse($rows->max('forecast_at'))->toDateString() : null,
            'currency'     => $supplier->currency?->code,
            'horizon_days' => self::HORIZON_DAYS,
            'months'       => $months->map(fn (Carbon $month) => [
                'key'      => $month->format('Y-m'),
                'products' => $products->where('order_month', $month->format('Y-m'))->count(),
                'cartons'  => (int) $products->where('order_month', $month->format('Y-m'))->sum('cartons'),
                'units'    => (int) $products->where('order_month', $month->format('Y-m'))->sum('units'),
                'value'    => round((float) $products->where('order_month', $month->format('Y-m'))->sum('value'), 2),
            ])->all(),
            'products'     => $products->values()->all(),
        ];
    }

    /**
     * One row per product of the supplier an organisation stocks, sells and still orders.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(OrgSupplier $orgSupplier, Carbon $today): Collection
    {
        $lead   = 'coalesce(os.measured_lead_time_days, os.estimated_lead_time_days, sp.measured_lead_time_days, sp.estimated_lead_time_days, '.(int) GetSupplierLeadTime::run($orgSupplier)['days'].')';
        $coming = GetOrganisationStockCoverBuckets::comingSkosExpression('os');

        return GetSupplierStockCoverBuckets::make()->scopedQuery($orgSupplier)
            ->where('os.state', OrgStockStateEnum::ACTIVE->value)
            ->where('s.predicted_daily_usage', '>', 0)
            ->whereRaw('coalesce(os.is_excluded_from_auto_ordering, false) = false')
            ->whereRaw('not '.Tag::outOfOrderingSeasonSql('os.stock_id', $lead))
            ->selectRaw("sp.id as supplier_product_id,
                sp.cost,
                sp.units_per_carton,
                sp.minimum_carton_order,
                greatest(coalesce(os.packed_in, 1), 1) as packed_in,
                $lead as lead_days,
                s.predicted_daily_usage / ".GetAgentSalesProducts::suppliersOfSql('os.id')." as daily_skos,
                (greatest(os.quantity_available, 0) + $coming) / s.predicted_daily_usage as cover_days,
                greatest(os.quantity_available, 0) + $coming as held_skos,
                ".GetAgentSalesProducts::suppliersOfSql('os.id').' as sources,
                s.demand_forecast_hydrated_at as forecast_at')
            ->get()
            ->map(function (object $row) use ($orgSupplier, $today) {
                $leadDays  = (int) $row->lead_days;
                $orderIn   = max(0, (int) floor((float) $row->cover_days) - $leadDays);
                $neededSkos = max(0.0, (float) $row->daily_skos * (self::HORIZON_DAYS + $leadDays) - (float) $row->held_skos / max(1, (int) $row->sources));

                return [
                    'supplier_product_id'  => (int) $row->supplier_product_id,
                    'organisation'         => $orgSupplier->organisation->name,
                    'cost'                 => (float) $row->cost,
                    'units_per_carton'     => (int) ($row->units_per_carton ?? 0),
                    'minimum_carton_order' => (int) ($row->minimum_carton_order ?? 0),
                    'order_on'             => $orderIn <= self::HORIZON_DAYS ? $today->copy()->addDays($orderIn) : null,
                    'units'                => $orderIn <= self::HORIZON_DAYS ? $neededSkos * (int) $row->packed_in : 0.0,
                    'monthly_units'        => (float) $row->daily_skos * (int) $row->packed_in * 30,
                    'forecast_at'          => $row->forecast_at,
                ];
            });
    }

    /**
     * The organisations' rows of each product added up: the first expected order, and the quantity in whole cartons.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, Carbon>  $months
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function byProduct(Collection $rows, Collection $months): Collection
    {
        $lastMonth = $months->last()->format('Y-m');
        $grouped   = $rows->groupBy('supplier_product_id');
        $products  = SupplierProduct::whereIn('id', $grouped->keys())->with(['image', 'tradeUnits.image'])->get()->keyBy('id');

        return $grouped
            ->map(function (Collection $productRows, int $supplierProductId) use ($products, $lastMonth) {
                $product   = $products->get($supplierProductId);
                $first     = $productRows->first();
                $ordering  = $productRows->filter(fn (array $row) => $row['order_on'] !== null && $row['units'] > 0);
                $orderOn   = $ordering->min('order_on');
                $perCarton = $first['units_per_carton'];
                $units     = (float) $ordering->sum('units');
                $cartons   = $perCarton > 0 && $units > 0 ? max((int) ceil($units / $perCarton), $first['minimum_carton_order']) : null;
                $units     = $cartons !== null ? $cartons * $perCarton : (int) ceil($units);
                $month     = $orderOn?->format('Y-m');
                $imageOwner = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

                return [
                    'id'               => $supplierProductId,
                    'code'             => $product?->code,
                    'name'             => $product?->name,
                    'image'            => $imageOwner?->imageSources(80, 80),
                    'order_on'         => $orderOn?->toDateString(),
                    'order_month'      => $month !== null && $month <= $lastMonth ? $month : null,
                    'units'            => $units,
                    'cartons'          => $cartons,
                    'units_per_carton' => $perCarton ?: null,
                    'value'            => round($units * $first['cost'], 2),
                    'monthly_units'    => (int) round($productRows->sum('monthly_units')),
                    'organisations'    => $ordering->pluck('organisation')->unique()->values()->all(),
                ];
            })
            ->filter(fn (array $product) => $product['monthly_units'] > 0)
            ->sortBy(fn (array $product) => [$product['order_on'] ?? '9999-12-31', -$product['value']])
            ->values();
    }
}
