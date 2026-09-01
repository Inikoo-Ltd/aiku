<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 1 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerOrderCapacity
{
    use AsObject;

    public const MIN_MONTHS = 3;
    // ponytail: 20% fair share is a guess, upgrade path is an org-level setting
    public const PARTNER_SHARE_OF_EMPTY_LOCATIONS = 0.2;
    public const WAREHOUSE_FULL_FREE_RATIO = 0.05;

    /**
     * What this partner historically delivers to us per 30 days (our achieved share of
     * their output, not their total capacity) vs the open shopping list, plus warehouse
     * slot headroom.
     * All money figures are in the partner's currency: stock delivery cost_total is stored
     * in the delivery (= partner) currency, matching the shopping list value built from
     * the seller shop's product prices.
     *
     * @return array{
     *     partner_capacity: array{delivers_to_us_per_30d: float|null, source: 'measured'|'estimate', samples: int},
     *     list: array{value: float, lines: int},
     *     warehouse: array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, partner_share_used: int, partner_share_limit: int},
     *     blocked: array{at_capacity: bool, warehouse_full: bool}
     * }
     */
    public function handle(OrgPartner $orgPartner): array
    {
        [$capacity, $warehouse] = Cache::remember(
            "partner-order-capacity:{$orgPartner->id}",
            now()->addMinutes(15),
            fn () => [$this->partnerCapacity($orgPartner), $this->warehouse($orgPartner)]
        );
        $list = $this->openListValue($orgPartner);

        return [
            'partner_capacity' => $capacity,
            'list'             => $list,
            'warehouse'        => $warehouse,
            'blocked'          => [
                'at_capacity'    => $capacity['delivers_to_us_per_30d'] !== null && $list['value'] >= $capacity['delivers_to_us_per_30d'],
                'warehouse_full' => $warehouse['free_ratio'] !== null && $warehouse['free_ratio'] < self::WAREHOUSE_FULL_FREE_RATIO,
            ],
        ];
    }

    /**
     * @return array{delivers_to_us_per_30d: float|null, source: 'measured'|'estimate', samples: int}
     */
    protected function partnerCapacity(OrgPartner $orgPartner): array
    {
        $measured = DB::table('stock_deliveries')
            ->where('organisation_id', $orgPartner->organisation_id)
            ->where('partner_id', $orgPartner->partner_id)
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(booked_in_at, placed_at, date) >= ?', [now()->subMonths(6)])
            ->selectRaw("count(*) as samples,
                count(distinct date_trunc('month', coalesce(booked_in_at, placed_at, date))) as months,
                sum(cost_total) as total")
            ->first();

        $cycleShare = $this->orderCycleShare($orgPartner);

        if ((int) $measured->months >= self::MIN_MONTHS) {
            return [
                'delivers_to_us_per_30d' => round((float) $measured->total / (int) $measured->months * $cycleShare, 2),
                'source'        => 'measured',
                'samples'       => (int) $measured->samples,
            ];
        }

        return $this->bootstrapCapacity($orgPartner, (int) $measured->samples, $cycleShare);
    }

    /**
     * A shopping list should hold one order cycle of demand (lead time plus a review week),
     * never a full month at once.
     */
    protected function orderCycleShare(OrgPartner $orgPartner): float
    {
        return min(1, (GetPartnerLeadTime::run($orgPartner)['days'] + 7) / 30);
    }

    /**
     * Deterministic bootstrap while delivery history is thin: the value of what the demand
     * forecaster says we actually need from this partner. Nobody can edit it.
     *
     * @return array{delivers_to_us_per_30d: float|null, source: string, samples: int}
     */
    protected function bootstrapCapacity(OrgPartner $orgPartner, int $samples, float $cycleShare): array
    {
        $sales = $this->salesDemandValue($orgPartner);

        return [
            'delivers_to_us_per_30d' => $sales > 0 ? round($sales * $cycleShare, 2) : null,
            'source'                 => $sales > 0 ? 'sales' : 'none',
            'samples'                => $samples,
        ];
    }

    /**
     * Deterministic bootstrap while delivery history is thin: what we actually dispatched of this
     * partner's products in the last 90 days, monthly, at the partner's prices. Real shipments,
     * so out-of-stock extrapolation and pack-size minimums never inflate it; nobody can edit it.
     */
    protected function salesDemandValue(OrgPartner $orgPartner): float
    {
        return round((float) DB::table('delivery_note_items as dni')
            ->join('org_stocks as os', function ($join) use ($orgPartner) {
                $join->on('os.id', 'dni.org_stock_id')
                    ->where('os.organisation_id', $orgPartner->organisation_id);
            })
            ->join('org_stocks as p', function ($join) use ($orgPartner) {
                $join->on('p.stock_id', 'os.stock_id')
                    ->where('p.organisation_id', $orgPartner->partner_id)
                    ->where('p.state', OrgStockStateEnum::ACTIVE->value);
            })
            ->where('dni.quantity_dispatched', '>', 0)
            ->where('dni.created_at', '>=', now()->subDays(90))
            ->selectRaw("coalesce(sum(dni.quantity_dispatched * coalesce((select pr.price / nullif(phos.quantity, 0)
                from product_has_org_stocks phos
                join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
                where phos.org_stock_id = p.id limit 1), 0)) / 3, 0) as total")
            ->value('total'), 2);
    }

    /**
     * @return array{value: float, lines: int}
     */
    public function openListValue(OrgPartner $orgPartner): array
    {
        $row = DB::table('partner_shopping_list_items')
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as lines, coalesce(sum(quantity * coalesce('.$this->pricePerSkoSubQuery().', 0)), 0) as value')
            ->first();

        return [
            'value' => (float) $row->value,
            'lines' => (int) $row->lines,
        ];
    }

    public function pricePerSkoSubQuery(): string
    {
        return "(select pr.price / nullif(phos.quantity, 0)
            from product_has_org_stocks phos
            join products pr on pr.id = phos.product_id and pr.state = '".ProductStateEnum::ACTIVE->value."'
            join org_stocks sos on sos.id = phos.org_stock_id
            where sos.stock_id = partner_shopping_list_items.stock_id
                and sos.organisation_id = partner_shopping_list_items.partner_organisation_id
            limit 1)";
    }

    /**
     * Slot-count proxy: no volume data exists, a location is one slot.
     *
     * @return array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, partner_share_used: int, partner_share_limit: int}
     */
    protected function warehouse(OrgPartner $orgPartner): array
    {
        $locations = DB::table('locations')
            ->join('warehouses', 'warehouses.id', 'locations.warehouse_id')
            ->where('warehouses.organisation_id', $orgPartner->organisation_id)
            ->whereNull('locations.deleted_at')
            ->selectRaw('count(*) as total, count(*) filter (where locations.is_empty) as empty')
            ->first();

        $inboundOpenPoLines = (int) DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('po.organisation_id', $orgPartner->organisation_id)
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
            'partner_share_used'    => $this->partnerNeverStockedOpenLines($orgPartner),
            'partner_share_limit'   => (int) floor($empty * self::PARTNER_SHARE_OF_EMPTY_LOCATIONS),
        ];
    }

    protected function partnerNeverStockedOpenLines(OrgPartner $orgPartner): int
    {
        return (int) DB::table('partner_shopping_list_items')
            ->join('org_stocks', 'org_stocks.id', 'partner_shopping_list_items.org_stock_id')
            ->where('partner_shopping_list_items.org_partner_id', $orgPartner->id)
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('location_org_stocks')
                    ->whereColumn('location_org_stocks.org_stock_id', 'org_stocks.id');
            })
            ->count();
    }

    public static function isExemptFromCap(OrgPartner $orgPartner, OrgStock $sellerOrgStock): bool
    {
        $buyerOrgStock = OrgStock::where('organisation_id', $orgPartner->organisation_id)
            ->where('stock_id', $sellerOrgStock->stock_id)
            ->first();

        if (!$buyerOrgStock) {
            return false;
        }

        return (float) $buyerOrgStock->quantity_available <= 0
            || $buyerOrgStock->health_rank === HealthRankEnum::A;
    }

    public static function isNeverStocked(OrgPartner $orgPartner, OrgStock $sellerOrgStock): bool
    {
        return !OrgStock::where('organisation_id', $orgPartner->organisation_id)
            ->where('stock_id', $sellerOrgStock->stock_id)
            ->exists();
    }

    public static function guardAdd(OrgPartner $orgPartner, OrgStock $sellerOrgStock): void
    {
        $capacity = static::run($orgPartner);

        if ($capacity['blocked']['at_capacity'] && !static::isExemptFromCap($orgPartner, $sellerOrgStock)) {
            abort(422, __(
                'Shopping list is at the level :partner historically delivers to us monthly (:cap :currency). Remove or deprioritize items first — only A-rank or out-of-stock items can be added past the cap.',
                [
                    'partner'  => $orgPartner->partner->name,
                    'cap'      => number_format((float) $capacity['partner_capacity']['delivers_to_us_per_30d'] * $orgPartner->exchangeToOrgCurrency(), 2),
                    'currency' => $orgPartner->organisation->currency->code,
                ]
            ));
        }

        if ($capacity['warehouse']['total_locations'] > 0 && static::isNeverStocked($orgPartner, $sellerOrgStock)) {
            if ($capacity['blocked']['warehouse_full']) {
                abort(422, __("Warehouse has fewer than 5% locations free — new products can't be added until space frees up."));
            }

            $warehouse = $capacity['warehouse'];
            if ($warehouse['partner_share_used'] >= $warehouse['partner_share_limit']) {
                abort(422, __(
                    'This partner already claims its share of free warehouse space (:n of :m free locations) — other suppliers need room too.',
                    ['n' => $warehouse['partner_share_used'], 'm' => $warehouse['empty_locations']]
                ));
            }
        }
    }
}
