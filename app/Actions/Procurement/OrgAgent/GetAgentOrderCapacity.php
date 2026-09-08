<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\HealthRankEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentOrderCapacity
{
    use AsObject;

    public const MIN_MONTHS = 3;
    // ponytail: 20% fair share is a guess, upgrade path is an org-level setting
    public const AGENT_SHARE_OF_EMPTY_LOCATIONS = 0.2;
    public const WAREHOUSE_FULL_FREE_RATIO = 0.05;

    /**
     * What this agent historically lands for us per 30 days versus the open shopping list, plus
     * warehouse slot headroom. Sub-suppliers invoice in their own currencies, so every figure is
     * converted to the organisation's currency: that is the only denominator an agent buyer can
     * compare a mixed list against.
     *
     * @return array{
     *     agent_capacity: array{lands_for_us_per_30d: float|null, source: 'measured'|'sales'|'none', samples: int},
     *     list: array{value: float, lines: int, units: float},
     *     warehouse: array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, agent_share_used: int, agent_share_limit: int},
     *     currency: string,
     *     blocked: array{at_capacity: bool, warehouse_full: bool}
     * }
     */
    public function handle(OrgAgent $orgAgent): array
    {
        [$capacity, $warehouse] = Cache::remember(
            "agent-order-capacity:{$orgAgent->id}",
            now()->addMinutes(15),
            fn () => [$this->agentCapacity($orgAgent), $this->warehouse($orgAgent)]
        );
        $list = $this->openListValue($orgAgent);

        return [
            'agent_capacity' => $capacity,
            'list'           => $list,
            'warehouse'      => $warehouse,
            'currency'       => $orgAgent->organisation->currency->code,
            'blocked'        => [
                'at_capacity'    => $capacity['lands_for_us_per_30d'] !== null && $list['value'] >= $capacity['lands_for_us_per_30d'],
                'warehouse_full' => $warehouse['free_ratio'] !== null && $warehouse['free_ratio'] < self::WAREHOUSE_FULL_FREE_RATIO,
            ],
        ];
    }

    /**
     * @return array{lands_for_us_per_30d: float|null, source: 'measured'|'sales'|'none', samples: int}
     */
    protected function agentCapacity(OrgAgent $orgAgent): array
    {
        $measured = DB::table('stock_deliveries')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(booked_in_at, placed_at, date) >= ?', [now()->subMonths(6)])
            ->selectRaw("count(*) as samples,
                count(distinct date_trunc('month', coalesce(booked_in_at, placed_at, date))) as months,
                coalesce(sum(cost_total * coalesce(org_exchange, 1)), 0) as total")
            ->first();

        $cycleShare = $this->orderCycleShare($orgAgent);

        if ((int) $measured->months >= self::MIN_MONTHS) {
            return [
                'lands_for_us_per_30d' => round((float) $measured->total / (int) $measured->months * $cycleShare, 2),
                'source'               => 'measured',
                'samples'              => (int) $measured->samples,
            ];
        }

        $sales = $this->salesDemandValue($orgAgent);

        return [
            'lands_for_us_per_30d' => $sales > 0 ? round($sales * $cycleShare, 2) : null,
            'source'               => $sales > 0 ? 'sales' : 'none',
            'samples'              => (int) $measured->samples,
        ];
    }

    /**
     * A shopping list holds one order cycle of demand, never a full month at once. Agent cycles run
     * to months rather than weeks, so this is capped at a quarter instead of the partner's month.
     */
    protected function orderCycleShare(OrgAgent $orgAgent): float
    {
        return min(3, (GetAgentLeadTimes::run($orgAgent)['agent']['days'] + 7) / 30);
    }

    /**
     * Deterministic bootstrap while delivery history is thin: what we actually dispatched of this
     * agent's products in the last 90 days, monthly, at supplier cost. Real shipments, so
     * out-of-stock extrapolation and carton minimums never inflate it; nobody can edit it.
     */
    protected function salesDemandValue(OrgAgent $orgAgent): float
    {
        $rows = DB::table('delivery_note_items as dni')
            ->join('org_stock_has_org_supplier_products as link', 'link.org_stock_id', 'dni.org_stock_id')
            ->join('org_supplier_products as osp', function ($join) use ($orgAgent) {
                $join->on('osp.id', 'link.org_supplier_product_id')
                    ->where('osp.org_agent_id', $orgAgent->id)
                    ->where('osp.state', OrgSupplierProductStateEnum::ACTIVE->value);
            })
            ->join('supplier_products as sp', 'sp.id', 'osp.supplier_product_id')
            ->where('dni.quantity_dispatched', '>', 0)
            ->where('dni.created_at', '>=', now()->subDays(90))
            ->groupBy('sp.currency_id')
            ->selectRaw('sp.currency_id, coalesce(sum(dni.quantity_dispatched * coalesce(sp.cost, 0)), 0) as total')
            ->get();

        return round($this->toOrganisationCurrency($orgAgent, $rows) / 3, 2);
    }

    /**
     * @return array{value: float, lines: int, units: float}
     */
    public function openListValue(OrgAgent $orgAgent): array
    {
        $rows = DB::table('shopping_list_items as sli')
            ->join('supplier_products as sp', 'sp.id', 'sli.supplier_product_id')
            ->where('sli.organisation_id', $orgAgent->organisation_id)
            ->where('sli.agent_id', $orgAgent->agent_id)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->groupBy('sp.currency_id')
            ->selectRaw('sp.currency_id, count(*) as lines, coalesce(sum(sli.quantity_units), 0) as units,
                coalesce(sum(sli.quantity_units * coalesce(sp.cost, 0)), 0) as total')
            ->get();

        return [
            'value' => round($this->toOrganisationCurrency($orgAgent, $rows), 2),
            'lines' => (int) $rows->sum('lines'),
            'units' => (float) $rows->sum('units'),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $rows rows carrying currency_id and total
     */
    protected function toOrganisationCurrency(OrgAgent $orgAgent, $rows): float
    {
        $organisationCurrency = $orgAgent->organisation->currency;

        return (float) $rows->sum(function ($row) use ($organisationCurrency) {
            if (!$row->currency_id) {
                return 0;
            }

            $exchange = GetCurrencyExchange::run(Currency::find($row->currency_id), $organisationCurrency);

            return $exchange === null ? 0 : (float) $row->total * $exchange;
        });
    }

    /**
     * Slot-count proxy: no volume data exists, a location is one slot. The warehouse is shared, so
     * every source competes for the same free space.
     *
     * @return array{total_locations: int, empty_locations: int, free_ratio: float|null, inbound_open_po_lines: int, agent_share_used: int, agent_share_limit: int}
     */
    protected function warehouse(OrgAgent $orgAgent): array
    {
        $locations = DB::table('locations')
            ->join('warehouses', 'warehouses.id', 'locations.warehouse_id')
            ->where('warehouses.organisation_id', $orgAgent->organisation_id)
            ->whereNull('locations.deleted_at')
            ->selectRaw('count(*) as total, count(*) filter (where locations.is_empty) as empty')
            ->first();

        $inboundOpenPoLines = (int) DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('po.organisation_id', $orgAgent->organisation_id)
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
            'agent_share_used'      => $this->agentNeverStockedOpenLines($orgAgent),
            'agent_share_limit'     => (int) floor($empty * self::AGENT_SHARE_OF_EMPTY_LOCATIONS),
        ];
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

    /**
     * The budget is the agent's, but the warehouse is everyone's: a new product still has to fit
     * this agent's fair share of free slots, and an item we have run out of or rank A always gets
     * through the budget cap.
     */
    public static function guardAdd(OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct): void
    {
        $capacity = static::run($orgAgent);

        if ($capacity['blocked']['at_capacity'] && !static::isExemptFromCap($orgSupplierProduct)) {
            abort(422, __(
                'Shopping list is at the level :agent historically lands for us monthly (:cap :currency). Remove or deprioritize items first — only A-rank or out-of-stock items can be added past the cap.',
                [
                    'agent'    => $orgAgent->agent->name,
                    'cap'      => number_format((float) $capacity['agent_capacity']['lands_for_us_per_30d'], 2),
                    'currency' => $capacity['currency'],
                ]
            ));
        }

        if ($capacity['warehouse']['total_locations'] > 0 && !static::linkedOrgStock($orgSupplierProduct)) {
            if ($capacity['blocked']['warehouse_full']) {
                abort(422, __("Warehouse has fewer than 5% locations free — new products can't be added until space frees up."));
            }

            $warehouse = $capacity['warehouse'];
            if ($warehouse['agent_share_used'] >= $warehouse['agent_share_limit']) {
                abort(422, __(
                    'This agent already claims its share of free warehouse space (:n of :m free locations) — other sources need room too.',
                    ['n' => $warehouse['agent_share_used'], 'm' => $warehouse['empty_locations']]
                ));
            }
        }
    }

    protected function agentNeverStockedOpenLines(OrgAgent $orgAgent): int
    {
        return (int) DB::table('shopping_list_items as sli')
            ->where('sli.organisation_id', $orgAgent->organisation_id)
            ->where('sli.agent_id', $orgAgent->agent_id)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('org_stock_has_org_supplier_products as link')
                    ->join('org_stocks', 'org_stocks.id', 'link.org_stock_id')
                    ->where('org_stocks.state', OrgStockStateEnum::ACTIVE->value)
                    ->whereColumn('link.org_supplier_product_id', 'sli.org_supplier_product_id');
            })
            ->count();
    }
}
