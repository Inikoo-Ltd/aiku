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
use App\Actions\Procurement\ProjectOrganisationStockOuts;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Helpers\Tag;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What we expect to order from the supplier in the next three months, so it can buy its raw materials in time: a
 * system estimate, not yet reviewed by a buyer, and never an order. Worked out for each organisation from its sales
 * forecast for the SKOs the supplier's products feed, then added up per product.
 *
 * Per organisation: the stock we hold plus what is already coming (open deliveries and submitted purchase orders)
 * lasts cover = held / daily usage days; we expect to order the lead time before that runs out, and to need the
 * usage over the horizon plus the lead time, less what we hold. The likely date and quantity use the forecast usage;
 * the window and the low and high ends use the daily usage less and plus the 80% band of the forecast (ProjectOrganisationStockOuts::BAND_Z × its daily deviation), the same band the
 * stock-out projection uses, so the window and range come from the forecast's own spread, not a guessed score. Each
 * end is rounded up to whole cartons and to the minimum carton order.
 *
 * Our stock levels, sales, costs and margins stay out: the supplier sees only when, how much of its own products,
 * at its own prices. A SKO bought from several suppliers gives each an even share, as the sales figures do.
 */
class GetSupplierPortalOrderPlan
{
    use AsObject;

    public const int HORIZON_DAYS = 90;

    public const int STALE_FORECAST_DAYS = 14;

    private const array BORROWED_SOURCES = ['family', 'siblings'];

    private const array IRREGULAR_SOURCES = ['croston'];

    /**
     * @return array{as_of: string|null, currency: string|null, horizon_days: int, months: array<int, array<string, mixed>>, products: array<int, array<string, mixed>>}
     */
    public function handle(Supplier $supplier): array
    {
        $today  = now()->startOfDay();
        $months = collect(range(0, (int) $today->copy()->startOfMonth()->diffInMonths($today->copy()->addDays(self::HORIZON_DAYS)->startOfMonth())))
            ->map(fn (int $offset) => $today->copy()->startOfMonth()->addMonthsNoOverflow($offset));

        $rows     = $supplier->orgSuppliers()->with('organisation')->get()
            ->flatMap(fn (OrgSupplier $orgSupplier) => $this->rows($orgSupplier, $today));
        $products = $this->byProduct($rows, $months, $this->openOrders($supplier, $rows->pluck('supplier_product_id')->unique()->all()));

        return [
            'as_of'        => $rows->max('forecast_at') ? Carbon::parse($rows->max('forecast_at'))->toDateString() : null,
            'currency'     => $supplier->currency?->code,
            'horizon_days' => self::HORIZON_DAYS,
            'months'       => $months->map(function (Carbon $month) use ($products) {
                $inMonth = $products->where('order_month', $month->format('Y-m'));

                return [
                    'key'        => $month->format('Y-m'),
                    'products'   => $inMonth->count(),
                    'cartons'    => (int) $inMonth->sum('cartons'),
                    'units'      => (int) $inMonth->sum('units'),
                    'value'      => round((float) $inMonth->sum('value'), 2),
                    'value_low'  => round((float) $inMonth->sum('value_low'), 2),
                    'value_high' => round((float) $inMonth->sum('value_high'), 2),
                ];
            })->all(),
            'products'     => $products->values()->all(),
        ];
    }

    /**
     * One row per product of the supplier an organisation stocks and still orders.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(OrgSupplier $orgSupplier, Carbon $today): Collection
    {
        $lead    = 'coalesce(os.measured_lead_time_days, os.estimated_lead_time_days, sp.measured_lead_time_days, sp.estimated_lead_time_days, '.(int) GetSupplierLeadTime::run($orgSupplier)['days'].')';
        $coming  = GetOrganisationStockCoverBuckets::comingSkosExpression('os');
        $sources = GetAgentSalesProducts::suppliersOfSql('os.id');

        return GetSupplierStockCoverBuckets::make()->scopedQuery($orgSupplier)
            ->whereNotNull('os.id')
            ->where('os.state', OrgStockStateEnum::ACTIVE->value)
            ->whereRaw('coalesce(os.is_excluded_from_auto_ordering, false) = false')
            ->selectRaw("sp.id as supplier_product_id,
                sp.cost,
                sp.units_per_carton,
                sp.minimum_carton_order,
                greatest(coalesce(os.packed_in, 1), 1) as packed_in,
                $lead as lead_days,
                (os.measured_lead_time_days is null and os.estimated_lead_time_days is null and sp.measured_lead_time_days is null and sp.estimated_lead_time_days is null) as lead_is_default,
                ".Tag::outOfOrderingSeasonSql('os.stock_id', $lead)." as out_of_season,
                s.predicted_daily_usage,
                s.demand_variability,
                s.forecast_source,
                s.demand_forecast->'weeks' as weeks,
                s.demand_forecast->>'from' as forecast_from,
                s.demand_forecast_hydrated_at as forecast_at,
                greatest(os.quantity_available, 0) + $coming as held_skos,
                $sources as sources")
            ->get()
            ->map(fn (object $row) => [
                'supplier_product_id'  => (int) $row->supplier_product_id,
                'organisation'         => $orgSupplier->organisation->name,
                'cost'                 => (float) $row->cost,
                'units_per_carton'     => (int) ($row->units_per_carton ?? 0),
                'minimum_carton_order' => (int) ($row->minimum_carton_order ?? 0),
                'forecast_at'          => $row->forecast_at,
                ...$this->estimate($row, $today),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function estimate(object $row, Carbon $today): array
    {
        $flags = $this->flags($row, $today);
        $none  = fn (string $reason) => ['status' => 'no_order', 'reason' => $reason, 'flags' => $flags, 'order_on' => null, 'order_from' => null, 'order_to' => null, 'arrive_from' => null, 'arrive_to' => null, 'units_low' => 0.0, 'units' => 0.0, 'units_high' => 0.0, 'monthly_units' => 0.0];

        if ($row->predicted_daily_usage === null) {
            return $none('no_forecast');
        }

        $usage = (float) $row->predicted_daily_usage;
        if ($usage <= 0) {
            return $none('no_sales');
        }

        $shares      = max(1, (int) $row->sources);
        $packedIn    = (int) $row->packed_in;
        $leadDays    = (int) $row->lead_days;
        $held        = max(0.0, (float) $row->held_skos);
        $spread      = $this->spread($row, $today);
        $monthly     = round($usage / $shares * $packedIn * 30, 1);

        if ($row->out_of_season) {
            return [...$none('out_of_season'), 'monthly_units' => $monthly];
        }

        $orderIn = fn (float $dailyUsage) => $dailyUsage > 0 ? max(0, (int) floor($held / $dailyUsage) - $leadDays) : null;
        $needed  = fn (float $dailyUsage) => max(0.0, ($dailyUsage * (self::HORIZON_DAYS + $leadDays) - $held) / $shares) * $packedIn;

        $likely   = $orderIn($usage);
        $earliest = $orderIn($usage * (1 + $spread));
        $latest   = $orderIn($usage * (1 - $spread));

        if ($likely > self::HORIZON_DAYS || $needed($usage) <= 0) {
            return [...$none('covered'), 'monthly_units' => $monthly];
        }

        $latest = $latest === null ? self::HORIZON_DAYS : min($latest, self::HORIZON_DAYS);

        return [
            'status'        => 'estimate',
            'reason'        => null,
            'flags'         => $flags,
            'order_on'      => $today->copy()->addDays($likely),
            'order_from'    => $today->copy()->addDays($earliest),
            'order_to'      => $today->copy()->addDays($latest),
            'arrive_from'   => $today->copy()->addDays($earliest + $leadDays),
            'arrive_to'     => $today->copy()->addDays($latest + $leadDays),
            'units_low'     => $needed($usage * (1 - $spread)),
            'units'         => $needed($usage),
            'units_high'    => $needed($usage * (1 + $spread)),
            'monthly_units' => $monthly,
        ];
    }

    /**
     * The forecast's 80% band as a share of its daily usage, at most 1 so the low end never goes below nothing.
     */
    private function spread(object $row, Carbon $today): float
    {
        [$demand, $variance] = ProjectOrganisationStockOuts::dailyDemand($row, $today);

        return $demand > 0 ? min(1.0, ProjectOrganisationStockOuts::BAND_Z * sqrt(max(0.0, $variance)) / $demand) : 0.0;
    }

    /**
     * What makes an estimate less reliable, for whoever reads it to weigh it.
     *
     * @return list<string>
     */
    private function flags(object $row, Carbon $today): array
    {
        $flags = [];

        if ($row->predicted_daily_usage !== null) {
            if (in_array($row->forecast_source, self::BORROWED_SOURCES, true)) {
                $flags[] = 'little_history';
            }
            if (in_array($row->forecast_source, self::IRREGULAR_SOURCES, true)) {
                $flags[] = 'irregular_sales';
            }
            if ($row->forecast_at === null || Carbon::parse($row->forecast_at)->lt($today->copy()->subDays(self::STALE_FORECAST_DAYS))) {
                $flags[] = 'stale_forecast';
            }
        }
        if ($row->lead_is_default) {
            $flags[] = 'lead_time_estimated';
        }
        if ((int) ($row->units_per_carton ?? 0) <= 0) {
            $flags[] = 'no_carton_size';
        }

        return $flags;
    }

    /**
     * The open purchase orders already sent to the supplier with each product on them, as the portal lists them.
     *
     * @param  array<int, int>  $supplierProductIds
     *
     * @return Collection<int, Collection<int, object>>
     */
    private function openOrders(Supplier $supplier, array $supplierProductIds): Collection
    {
        if (!$supplierProductIds) {
            return collect();
        }

        return DB::table('purchase_order_transactions as t')
            ->join('purchase_orders as po', 'po.id', 't.purchase_order_id')
            ->where('po.parent_type', 'OrgSupplier')
            ->whereIn('po.parent_id', DB::table('org_suppliers')->where('supplier_id', $supplier->id)->select('id'))
            ->whereIn('po.state', PurchaseOrderStateEnum::openValues())
            ->whereNull('po.deleted_at')
            ->whereNull('t.deleted_at')
            ->whereNotIn('t.state', [PurchaseOrderTransactionStateEnum::CANCELLED->value, PurchaseOrderTransactionStateEnum::NOT_RECEIVED->value])
            ->whereIn('t.supplier_product_id', $supplierProductIds)
            ->selectRaw('t.supplier_product_id, po.id, po.reference, sum(t.quantity_ordered) as units')
            ->groupBy('t.supplier_product_id', 'po.id', 'po.reference')
            ->orderBy('po.id')
            ->get()
            ->groupBy('supplier_product_id');
    }

    private function cartons(float $units, int $perCarton, int $minimum): ?int
    {
        return $perCarton > 0 && $units > 0 ? max((int) ceil($units / $perCarton), $minimum) : null;
    }

    private function wholeUnits(float $units, ?int $cartons, int $perCarton): int
    {
        return $cartons !== null ? $cartons * $perCarton : (int) ceil($units);
    }

    /**
     * The organisations' rows of each product added up: the widest window, and each end of the range in whole cartons.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, Carbon>  $months
     * @param  Collection<int, Collection<int, object>>  $openOrders
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function byProduct(Collection $rows, Collection $months, Collection $openOrders): Collection
    {
        $lastMonth = $months->last()->format('Y-m');
        $grouped   = $rows->groupBy('supplier_product_id');
        $products  = SupplierProduct::whereIn('id', $grouped->keys())->with(['image', 'tradeUnits.image'])->get()->keyBy('id');

        return $grouped
            ->map(function (Collection $productRows, int $supplierProductId) use ($products, $lastMonth, $openOrders) {
                $product    = $products->get($supplierProductId);
                $first      = $productRows->first();
                $perCarton  = $first['units_per_carton'];
                $minimum    = $first['minimum_carton_order'];
                $estimates  = $productRows->where('status', 'estimate');
                $orderOn    = $estimates->min('order_on');
                $month      = $orderOn?->format('Y-m');
                $imageOwner = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

                $ends = collect(['units_low', 'units', 'units_high'])->mapWithKeys(function (string $end) use ($estimates, $perCarton, $minimum, $first) {
                    $cartons = $this->cartons((float) $estimates->sum($end), $perCarton, $minimum);
                    $units   = $this->wholeUnits((float) $estimates->sum($end), $cartons, $perCarton);
                    $suffix  = substr($end, 5);

                    return [
                        'units'.$suffix   => $units,
                        'cartons'.$suffix => $cartons,
                        'value'.$suffix   => round($units * $first['cost'], 2),
                    ];
                });

                return [
                    'id'               => $supplierProductId,
                    'code'             => $product?->code,
                    'name'             => $product?->name,
                    'image'            => $imageOwner?->imageSources(80, 80),
                    'status'           => $estimates->isNotEmpty() ? 'estimate' : 'no_order',
                    'reason'           => $estimates->isNotEmpty() ? null : $this->reason($productRows),
                    'flags'            => $productRows->pluck('flags')->flatten()->unique()->values()->all(),
                    'order_on'         => $orderOn?->toDateString(),
                    'order_from'       => $estimates->min('order_from')?->toDateString(),
                    'order_to'         => $estimates->max('order_to')?->toDateString(),
                    'arrive_from'      => $estimates->min('arrive_from')?->toDateString(),
                    'arrive_to'        => $estimates->max('arrive_to')?->toDateString(),
                    'order_month'      => $month !== null && $month <= $lastMonth ? $month : null,
                    ...$ends->all(),
                    'units_per_carton' => $perCarton ?: null,
                    'monthly_units'    => round((float) $productRows->sum('monthly_units'), 1),
                    'organisations'    => $productRows->map(fn (array $row) => [
                        'name'       => $row['organisation'],
                        'status'     => $row['status'],
                        'reason'     => $row['reason'],
                        'order_on'   => $row['order_on']?->toDateString(),
                        'order_from' => $row['order_from']?->toDateString(),
                        'order_to'   => $row['order_to']?->toDateString(),
                        'units_low'  => (int) ceil($row['units_low']),
                        'units_high' => (int) ceil($row['units_high']),
                    ])->sortBy('name')->values()->all(),
                    'open_orders'      => ($openOrders->get($supplierProductId) ?? collect())->map(fn (object $order) => [
                        'id'        => (int) $order->id,
                        'reference' => $order->reference,
                        'units'     => (int) round((float) $order->units),
                    ])->values()->all(),
                ];
            })
            ->sortBy(fn (array $product) => [$product['status'] === 'estimate' ? 0 : 1, $product['order_on'] ?? '9999-12-31', -$product['value']])
            ->values();
    }

    /**
     * Why nothing is expected, the most telling reason across the organisations first.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function reason(Collection $rows): string
    {
        foreach (['covered', 'out_of_season', 'no_sales', 'no_forecast'] as $reason) {
            if ($rows->contains('reason', $reason)) {
                return $reason;
            }
        }

        return 'no_sales';
    }
}
