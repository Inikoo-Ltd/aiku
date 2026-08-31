<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierOrderCapacity
{
    use AsObject;

    public const MIN_MONTHS = 3;
    // ponytail: 20% fair share is a guess, upgrade path is an org-level setting
    public const SUPPLIER_SHARE_OF_EMPTY_LOCATIONS = 0.2;
    public const WAREHOUSE_FULL_FREE_RATIO = 0.05;

    /**
     * What this supplier historically delivers to us per 30 days vs the open shopping list,
     * plus warehouse slot headroom. All money is in the supplier's own currency: stock delivery
     * cost_total is stored in the delivery currency and supplier_products.cost is the unit cost
     * the shopping list is valued at.
     *
     * @return array{
     *     supplier_capacity: array{delivers_to_us_per_30d: float|null, source: string, samples: int},
     *     list: array{value: float, lines: int},
     *     warehouse: array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, supplier_share_used: int, supplier_share_limit: int},
     *     blocked: array{at_capacity: bool, warehouse_full: bool}
     * }
     */
    public function handle(OrgSupplier $orgSupplier): array
    {
        [$capacity, $warehouse] = Cache::remember(
            "supplier-order-capacity:{$orgSupplier->id}",
            now()->addMinutes(15),
            fn () => [$this->supplierCapacity($orgSupplier), $this->warehouse($orgSupplier)]
        );
        $list = $this->openListValue($orgSupplier);

        return [
            'supplier_capacity' => $capacity,
            'list'              => $list,
            'warehouse'         => $warehouse,
            'blocked'           => [
                'at_capacity'    => $capacity['delivers_to_us_per_30d'] !== null && $list['value'] >= $capacity['delivers_to_us_per_30d'],
                'warehouse_full' => $warehouse['free_ratio'] !== null && $warehouse['free_ratio'] < self::WAREHOUSE_FULL_FREE_RATIO,
            ],
        ];
    }

    /**
     * @return array{delivers_to_us_per_30d: float|null, source: string, samples: int}
     */
    protected function supplierCapacity(OrgSupplier $orgSupplier): array
    {
        $measured = DB::table('stock_deliveries')
            ->where('parent_type', 'OrgSupplier')
            ->where('parent_id', $orgSupplier->id)
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(booked_in_at, placed_at, date) >= ?', [now()->subMonths(6)])
            ->selectRaw("count(*) as samples,
                count(distinct date_trunc('month', coalesce(booked_in_at, placed_at, date))) as months,
                coalesce(sum(cost_total), 0) as total")
            ->first();

        $cycleShare = $this->orderCycleShare($orgSupplier);

        if ((int) $measured->months >= self::MIN_MONTHS) {
            return [
                'delivers_to_us_per_30d' => round((float) $measured->total / (int) $measured->months * $cycleShare, 2),
                'source'                 => 'measured',
                'samples'                => (int) $measured->samples,
            ];
        }

        $sales = $this->salesDemandValue($orgSupplier);

        return [
            'delivers_to_us_per_30d' => $sales > 0 ? round($sales * $cycleShare, 2) : null,
            'source'                 => $sales > 0 ? 'sales' : 'none',
            'samples'                => (int) $measured->samples,
        ];
    }

    /**
     * A shopping list should hold one order cycle of demand (lead time plus a review week),
     * never a full month at once.
     */
    protected function orderCycleShare(OrgSupplier $orgSupplier): float
    {
        return min(1, (GetSupplierLeadTime::run($orgSupplier)['days'] + 7) / 30);
    }

    /**
     * Deterministic bootstrap while delivery history is thin: what we actually dispatched of the
     * org stocks this supplier feeds in the last 90 days, monthly, at the supplier's unit cost.
     * Real shipments, so out-of-stock extrapolation never inflates it; nobody can edit it.
     */
    protected function salesDemandValue(OrgSupplier $orgSupplier): float
    {
        return round((float) DB::table('delivery_note_items as dni')
            ->join('org_stock_has_org_supplier_products as link', 'link.org_stock_id', 'dni.org_stock_id')
            ->join('org_supplier_products as p', function ($join) use ($orgSupplier) {
                $join->on('p.id', 'link.org_supplier_product_id')
                    ->where('p.org_supplier_id', $orgSupplier->id)
                    ->where('p.state', OrgSupplierProductStateEnum::ACTIVE->value);
            })
            ->join('supplier_products as sp', function ($join) use ($orgSupplier) {
                $join->on('sp.id', 'p.supplier_product_id')
                    ->where('sp.supplier_id', $orgSupplier->supplier_id);
            })
            ->where('link.status', true)
            ->where('dni.quantity_dispatched', '>', 0)
            ->where('dni.created_at', '>=', now()->subDays(90))
            ->selectRaw('coalesce(sum(dni.quantity_dispatched * coalesce(sp.cost, 0)) / 3, 0) as total')
            ->value('total'), 2);
    }

    /**
     * @return array{value: float, lines: int}
     */
    public function openListValue(OrgSupplier $orgSupplier): array
    {
        $row = DB::table('shopping_list_items as sli')
            ->join('org_supplier_products as p', 'p.id', 'sli.org_supplier_product_id')
            ->join('supplier_products as sp', 'sp.id', 'sli.supplier_product_id')
            ->where('p.org_supplier_id', $orgSupplier->id)
            ->where('sp.supplier_id', $orgSupplier->supplier_id)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->selectRaw('count(*) as lines, coalesce(sum(sli.quantity_units * coalesce(sp.cost, 0)), 0) as value')
            ->first();

        return [
            'value' => (float) $row->value,
            'lines' => (int) $row->lines,
        ];
    }

    /**
     * Slot-count proxy: no volume data exists, a location is one slot. The warehouse is shared
     * across every source, so a supplier only ever claims its fair share of what is free.
     *
     * @return array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, supplier_share_used: int, supplier_share_limit: int}
     */
    protected function warehouse(OrgSupplier $orgSupplier): array
    {
        $locations = DB::table('locations')
            ->join('warehouses', 'warehouses.id', 'locations.warehouse_id')
            ->where('warehouses.organisation_id', $orgSupplier->organisation_id)
            ->whereNull('locations.deleted_at')
            ->selectRaw('count(*) as total, count(*) filter (where locations.is_empty) as empty')
            ->first();

        $inboundOpenPoLines = (int) DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('po.organisation_id', $orgSupplier->organisation_id)
            ->whereIn('po.state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
            ->whereNotIn('po.delivery_state', [
                PurchaseOrderDeliveryStateEnum::RECEIVED->value,
                PurchaseOrderDeliveryStateEnum::CHECKED->value,
                PurchaseOrderDeliveryStateEnum::PLACED->value,
                PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNull('po.deleted_at')
            ->whereNull('pot.deleted_at')
            ->count();

        $total = (int) $locations->total;
        $empty = (int) $locations->empty;

        return [
            'total_locations'       => $total,
            'empty_locations'       => $empty,
            'free_ratio'            => $total > 0 ? round($empty / $total, 4) : null,
            'inbound_open_po_lines' => $inboundOpenPoLines,
            'supplier_share_used'   => $this->supplierNeverStockedOpenLines($orgSupplier),
            'supplier_share_limit'  => (int) floor($empty * self::SUPPLIER_SHARE_OF_EMPTY_LOCATIONS),
        ];
    }

    protected function supplierNeverStockedOpenLines(OrgSupplier $orgSupplier): int
    {
        return (int) DB::table('shopping_list_items as sli')
            ->join('org_supplier_products as p', 'p.id', 'sli.org_supplier_product_id')
            ->where('p.org_supplier_id', $orgSupplier->id)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('org_stock_has_org_supplier_products as link')
                    ->join('location_org_stocks', 'location_org_stocks.org_stock_id', 'link.org_stock_id')
                    ->whereColumn('link.org_supplier_product_id', 'p.id')
                    ->where('link.status', true);
            })
            ->count();
    }

    public static function linkedOrgStock(OrgSupplierProduct $orgSupplierProduct): ?OrgStock
    {
        return OrgStock::query()
            ->join('org_stock_has_org_supplier_products as link', 'link.org_stock_id', 'org_stocks.id')
            ->where('link.org_supplier_product_id', $orgSupplierProduct->id)
            ->where('link.status', true)
            ->orderBy('link.local_priority')
            ->select('org_stocks.*')
            ->first();
    }

    public static function isExemptFromCap(OrgSupplierProduct $orgSupplierProduct): bool
    {
        $orgStock = static::linkedOrgStock($orgSupplierProduct);

        if (!$orgStock) {
            return false;
        }

        return (float) $orgStock->quantity_available <= 0
            || $orgStock->health_rank === HealthRankEnum::A;
    }

    public static function guardAdd(OrgSupplier $orgSupplier, OrgSupplierProduct $orgSupplierProduct): void
    {
        $capacity = static::run($orgSupplier);

        if ($capacity['blocked']['at_capacity'] && !static::isExemptFromCap($orgSupplierProduct)) {
            abort(422, __(
                'Shopping list is at the level :supplier historically delivers to us monthly (:cap :currency). Remove or deprioritize items first — only A-rank or out-of-stock items can be added past the cap.',
                [
                    'supplier' => $orgSupplier->supplier->name,
                    'cap'      => number_format((float) $capacity['supplier_capacity']['delivers_to_us_per_30d'], 2),
                    'currency' => $orgSupplier->supplier->currency->code,
                ]
            ));
        }

        if ($capacity['warehouse']['total_locations'] > 0 && !static::linkedOrgStock($orgSupplierProduct)) {
            if ($capacity['blocked']['warehouse_full']) {
                abort(422, __("Warehouse has fewer than 5% locations free — new products can't be added until space frees up."));
            }

            $warehouse = $capacity['warehouse'];
            if ($warehouse['supplier_share_used'] >= $warehouse['supplier_share_limit']) {
                abort(422, __(
                    'This supplier already claims its share of free warehouse space (:n of :m free locations) — other suppliers need room too.',
                    ['n' => $warehouse['supplier_share_used'], 'm' => $warehouse['empty_locations']]
                ));
            }
        }
    }
}
