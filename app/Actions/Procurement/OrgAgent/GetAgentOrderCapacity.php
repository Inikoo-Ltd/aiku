<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Helpers\Currency;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentOrderCapacity
{
    use AsObject;

    // ponytail: 20% fair share is a guess, upgrade path is an org-level setting
    public const AGENT_SHARE_OF_EMPTY_LOCATIONS = 0.2;
    public const WAREHOUSE_FULL_FREE_RATIO = 0.05;
    private const int HISTORY_MONTHS = 12;

    /**
     * How much this organisation can put on its draft orders to this agent this round, what is
     * already ordered and still to land, and warehouse slot headroom. Sub-suppliers invoice in their
     * own currencies, so every figure is converted to the organisation's currency: that is the only
     * denominator an agent buyer can compare a mixed order against.
     *
     * @return array{
     *     agent_capacity: array{lands_for_us_per_30d: float|null, source: 'measured'|'sales'|'none', samples: int, usual_month_orders: float|null, restock_out_of_stock: float, container_value: float|null},
     *     pipeline: array{waiting: float, being_made: float, on_the_way: float, total: float},
     *     container: array{label: string, cbm: float},
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
            'pipeline'       => $this->pipeline($orgAgent),
            'container'      => [
                'label' => OrgAgentOrderContainerEnum::labels()[$orgAgent->order_container->value],
                'cbm'   => $orgAgent->order_container->cbm(),
            ],
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
     * The budget of a round keeps the money flowing at the pace it has: what the organisation sends
     * this agent in a usual month, from the orders of the last twelve. It is a round, not a pipeline:
     * the busiest month or a whole lead time of orders would let one round hold several containers.
     * On top goes what it costs to restock what is out of stock now and not yet on an order, so past
     * under-ordering does not cap the catch-up.
     *
     * @return array{lands_for_us_per_30d: float|null, source: 'measured'|'sales'|'none', samples: int, usual_month_orders: float|null, restock_out_of_stock: float, container_value: float|null}
     */
    protected function agentCapacity(OrgAgent $orgAgent): array
    {
        $ordered = $this->sentOrders($orgAgent)
            ->where('submitted_at', '>=', now()->subMonths(self::HISTORY_MONTHS))
            ->selectRaw('count(*) as samples, coalesce(sum(cost_total * coalesce(org_exchange, 1)), 0) as total, min(submitted_at) as first_at')
            ->first();

        $usualMonth = null;
        $source     = 'none';

        if ((float) $ordered->total > 0) {
            $months     = min(self::HISTORY_MONTHS, max(1, (int) ceil(Carbon::parse($ordered->first_at)->diffInDays(now()) / 30)));
            $usualMonth = (float) $ordered->total / $months;
            $source     = 'measured';
        } elseif (($sales = $this->salesDemandValue($orgAgent)) > 0) {
            $usualMonth = $sales;
            $source     = 'sales';
        }

        $restock = (float) collect(SuggestAgentShoppingList::make()->lines($orgAgent, 'out'))->sum('cost');

        return [
            'lands_for_us_per_30d' => $usualMonth === null ? null : round($usualMonth + $restock, 2),
            'usual_month_orders'   => $usualMonth === null ? null : round($usualMonth, 2),
            'restock_out_of_stock' => round($restock, 2),
            'container_value'      => $this->containerValue($orgAgent),
            'source'               => $source,
            'samples'              => (int) $ordered->samples,
        ];
    }

    private function sentOrders(OrgAgent $orgAgent): Builder
    {
        return DB::table('purchase_orders')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('parent_type', 'OrgSupplier')
            ->whereNull('deleted_at')
            ->whereNotNull('submitted_at')
            ->whereNotIn('state', [PurchaseOrderStateEnum::IN_PROCESS->value, PurchaseOrderStateEnum::CANCELLED->value, PurchaseOrderStateEnum::NOT_RECEIVED->value]);
    }

    /**
     * What a container from this agent is worth to the organisation, to read the money in
     * containers. Deliveries come as full containers and odd parcels, so only the ones at least half
     * the size of the largest are averaged.
     */
    private function containerValue(OrgAgent $orgAgent): ?float
    {
        // ponytail: "half the largest" is a heuristic for a full container, upgrade path is a container type on the stock delivery
        $values = DB::table('stock_deliveries')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(booked_in_at, placed_at, date) >= ?', [now()->subMonths(self::HISTORY_MONTHS)])
            ->selectRaw('cost_total * coalesce(org_exchange, 1) as value')
            ->pluck('value')
            ->map(fn ($value) => (float) $value);

        $largest = (float) $values->max();

        return $largest > 0 ? round($values->filter(fn (float $value) => $value >= $largest / 2)->avg(), 2) : null;
    }

    /**
     * Already ordered from this agent and not landed yet, by how far along it is. Not cached: it
     * moves the moment an order is sent.
     *
     * @return array{waiting: float, being_made: float, on_the_way: float, total: float}
     */
    public function pipeline(OrgAgent $orgAgent): array
    {
        $dispatched = PurchaseOrderDeliveryStateEnum::DISPATCHED->value;
        $beingMade  = "'".implode("', '", [
            PurchaseOrderDeliveryStateEnum::IN_PROCESS->value,
            PurchaseOrderDeliveryStateEnum::CONFIRMED->value,
            PurchaseOrderDeliveryStateEnum::READY_TO_SHIP->value,
        ])."'";
        $value      = 'cost_total * coalesce(org_exchange, 1)';

        $row = $this->sentOrders($orgAgent)
            ->whereIn('state', PurchaseOrderStateEnum::openValues())
            ->selectRaw("coalesce(sum($value) filter (where state = '".PurchaseOrderStateEnum::SUBMITTED->value."' and delivery_state in ($beingMade)), 0) as waiting,
                coalesce(sum($value) filter (where state in (".PurchaseOrderStateEnum::agreedSql().") and delivery_state in ($beingMade)), 0) as being_made,
                coalesce(sum($value) filter (where delivery_state = '$dispatched'), 0) as on_the_way")
            ->first();

        return [
            'waiting'    => round((float) $row->waiting, 2),
            'being_made' => round((float) $row->being_made, 2),
            'on_the_way' => round((float) $row->on_the_way, 2),
            'total'      => round((float) $row->waiting + (float) $row->being_made + (float) $row->on_the_way, 2),
        ];
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

        $drafts = DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('po.organisation_id', $orgAgent->organisation_id)
            ->where('po.agent_id', $orgAgent->agent_id)
            ->where('po.parent_type', 'OrgSupplier')
            ->where('po.state', PurchaseOrderStateEnum::IN_PROCESS->value)
            ->whereNull('po.deleted_at')
            ->whereNull('pot.deleted_at')
            ->groupBy('po.currency_id')
            ->selectRaw('po.currency_id, count(*) as lines, coalesce(sum(pot.quantity_ordered), 0) as units, coalesce(sum(pot.net_amount), 0) as total')
            ->get();

        $rows = $rows->concat($drafts);

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
            ->whereIn('po.state', PurchaseOrderStateEnum::openValues())
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
            ->orderByDesc('link.local_priority')
            ->select('org_stocks.*')
            ->first();
    }

    /**
     * The budget is the agent's, but the warehouse is everyone's: a new product still has to fit
     * this agent's fair share of free slots, and an item we have run out of or rank A always gets
     * through the budget cap.
     */
    public static function guardAdd(OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct): void
    {
        $capacity = static::run($orgAgent);


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
