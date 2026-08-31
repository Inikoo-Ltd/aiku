<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:12:27 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Search\GetSearchDemandOpportunities;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Illuminate\Support\Facades\DB;
use App\Models\Helpers\Currency;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainDashboard extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use AsAction;
    use WithInertia;

    private int $staleDays = 60;
    private ?array $staleFilters = null;

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(app('group'), $request);
        $default            = (int) ($request->user()->settings['stale_orders_days'] ?? 60);
        $this->staleDays    = max(1, (int) $request->query('stale_days', $default ?: 60));
        $this->staleFilters = $request->user()->settings['stale_orders_filters'] ?? null;
    }

    private function getDashboardCards(): array
    {
        $stats = $this->group->supplyChainStats;

        return [
            $this->dashboardCard(
                __('Agents'),
                __('Active agents'),
                'fal fa-people-arrows',
                $stats->number_active_agents,
                'violet',
                'grp.supply-chain.agents.index'
            ),
            $this->dashboardCard(
                __('Suppliers'),
                __('Active free suppliers'),
                'fal fa-person-dolly',
                $stats->number_active_independent_suppliers,
                'emerald',
                'grp.supply-chain.suppliers.index',
                [
                    $this->dashboardMetric(
                        __('Through agents'),
                        $stats->number_active_suppliers_in_agents,
                        'grp.supply-chain.agent_suppliers.index'
                    ),
                ],
            ),
            $this->dashboardCard(
                __('Supplier Products'),
                __('Current supplier products'),
                'fal fa-box-usd',
                $stats->number_current_supplier_products,
                'amber',
                'grp.supply-chain.supplier_products.index',
                [
                    $this->dashboardMetric(__('Active'), $stats->number_supplier_products_state_active, 'grp.supply-chain.supplier_products.index', ['elements[state]' => 'active']),
                    $this->dashboardMetric(__('Discontinuing'), $stats->number_supplier_products_state_discontinuing, 'grp.supply-chain.supplier_products.index', ['elements[state]' => 'discontinuing']),
                ],
                ['elements[state]' => 'active,discontinuing']
            ),
            $this->dashboardCard(
                __('Agent Supplier Purchase Orders'),
                __('Purchase orders'),
                'fal fa-clipboard-list',
                AgentSupplierPurchaseOrder::where('group_id', $this->group->id)->count(),
                'indigo',
                'grp.supply-chain.agent_supplier_purchase_orders.index'
            ),
            $this->dashboardCard(
                __('Command & Control'),
                __('Monitor supplier purchase orders'),
                'fal fa-radar',
                null,
                'sky',
                'grp.supply-chain.control.dashboard'
            ),
            $this->dashboardCard(
                __('Shopping List Board'),
                __('Review procurement demand'),
                'fal fa-shopping-basket',
                null,
                'emerald',
                'grp.supply-chain.shopping_list.board'
            ),
        ];
    }

    /** @var array<string, float|null> */
    private array $fallbackExchangeRates = [];

    private function grpAmount(?string $amount, ?string $exchange, ?string $currencyCode): ?float
    {
        if ($amount === null) {
            return null;
        }
        if ($exchange !== null) {
            return round((float) $amount * (float) $exchange, 2);
        }
        if (!$currencyCode) {
            return null;
        }
        if (!array_key_exists($currencyCode, $this->fallbackExchangeRates)) {
            $currency = Currency::where('code', $currencyCode)->first();
            $this->fallbackExchangeRates[$currencyCode] = $currency
                ? GetCurrencyExchange::run($currency, $this->group->currency)
                : null;
        }
        $rate = $this->fallbackExchangeRates[$currencyCode];

        return $rate !== null ? round((float) $amount * $rate, 2) : null;
    }

    private function getStaleOrders(): array
    {
        $staleDate = now()->subDays($this->staleDays);

        $aspos = DB::table('agent_supplier_purchase_orders as aspo')
            ->join('suppliers', 'suppliers.id', '=', 'aspo.supplier_id')
            ->leftJoin('agents', 'agents.id', '=', 'suppliers.agent_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'aspo.currency_id')
            ->where('aspo.group_id', $this->group->id)
            ->whereNull('aspo.deleted_at')
            ->whereIn('aspo.state', [
                AgentSupplierPurchaseOrderStateEnum::SUBMITTED->value,
                AgentSupplierPurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->where(function ($query) use ($staleDate) {
                $query->where('aspo.deposit_paid_at', '<=', $staleDate)
                    ->orWhere(function ($subQuery) use ($staleDate) {
                        $subQuery->whereNull('aspo.deposit_paid_at')
                            ->where(DB::raw('coalesce(aspo.submitted_at, aspo.date)'), '<=', $staleDate);
                    });
            })
            ->where('aspo.number_stock_deliveries_state_received', 0)
            ->where('aspo.number_stock_deliveries_state_checked', 0)
            ->where('aspo.number_stock_deliveries_state_placed', 0)
            ->select([
                'aspo.id',
                'aspo.reference',
                'aspo.slug',
                'aspo.state',
                'aspo.cost_total',
                'aspo.grp_exchange',
                'aspo.deposit_amount',
                'aspo.deposit_paid_at',
                'aspo.number_stock_deliveries',
                DB::raw('coalesce(aspo.submitted_at, aspo.date) as ordered_at'),
                'currencies.code as currency_code',
                'suppliers.name as supplier_name',
                'suppliers.code as supplier_code',
                'suppliers.slug as supplier_slug',
                'agents.name as agent_name',
                'agents.code as agent_code',
            ])
            ->orderByRaw('aspo.deposit_paid_at asc nulls last, ordered_at asc')
            ->get()
            ->map(fn ($row) => [
                'type'             => 'aspo',
                'reference'        => $row->reference,
                'agent_name'       => $row->agent_name,
                'agent_code'       => $row->agent_code,
                'supplier_name'    => $row->supplier_name,
                'supplier_code'    => $row->supplier_code,
                'state'            => $row->state,
                'ordered_at'       => $row->ordered_at,
                'deposit_amount'   => $row->deposit_amount ? (float) $row->deposit_amount : null,
                'deposit_amount_grp' => $this->grpAmount($row->deposit_amount, $row->grp_exchange, $row->currency_code),
                'deposit_paid_at'  => $row->deposit_paid_at,
                'amount'           => $row->cost_total ? (float) $row->cost_total : null,
                'amount_grp'       => $this->grpAmount($row->cost_total, $row->grp_exchange, $row->currency_code),
                'currency'         => $row->currency_code,
                'has_deliveries'   => $row->number_stock_deliveries > 0,
                'route'            => [
                    'name'       => 'grp.supply-chain.agent_supplier_purchase_orders.show',
                    'parameters' => ['agentSupplierPurchaseOrder' => $row->slug],
                ],
            ]);

        $purchaseOrders = DB::table('purchase_orders as po')
            ->join('organisations', 'organisations.id', '=', 'po.organisation_id')
            ->leftJoin('currencies', 'currencies.id', '=', 'po.currency_id')
            ->where('po.group_id', $this->group->id)
            ->whereNull('po.deleted_at')
            ->whereIn('po.state', [
                PurchaseOrderStateEnum::SUBMITTED->value,
                PurchaseOrderStateEnum::CONFIRMED->value,
            ])
            ->where(DB::raw('coalesce(po.submitted_at, po.date)'), '<=', $staleDate)
            ->where('po.number_stock_deliveries_state_received', 0)
            ->where('po.number_stock_deliveries_state_checked', 0)
            ->where('po.number_stock_deliveries_state_placed', 0)
            ->select([
                'po.id',
                'po.reference',
                'po.slug',
                'po.state',
                'po.cost_total',
                'po.grp_exchange',
                'po.parent_name',
                'po.parent_code',
                'po.number_stock_deliveries',
                DB::raw('coalesce(po.submitted_at, po.date) as ordered_at'),
                'currencies.code as currency_code',
                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
            ])
            ->orderBy('ordered_at')
            ->get()
            ->map(fn ($row) => [
                'type'             => 'po',
                'reference'        => $row->reference,
                'agent_name'       => null,
                'agent_code'       => null,
                'supplier_name'    => $row->parent_name,
                'supplier_code'    => $row->parent_code,
                'organisation'     => $row->organisation_code,
                'state'            => $row->state,
                'ordered_at'       => $row->ordered_at,
                'deposit_amount'   => null,
                'deposit_amount_grp' => null,
                'deposit_paid_at'  => null,
                'amount'           => $row->cost_total ? (float) $row->cost_total : null,
                'amount_grp'       => $this->grpAmount($row->cost_total, $row->grp_exchange, $row->currency_code),
                'currency'         => $row->currency_code,
                'has_deliveries'   => $row->number_stock_deliveries > 0,
                'route'            => [
                    'name'       => 'grp.org.procurement.purchase_orders.show',
                    'parameters' => [
                        'organisation'  => $row->organisation_slug,
                        'purchaseOrder' => $row->slug,
                    ],
                ],
            ]);

        return [
            'threshold_days' => $this->staleDays,
            'grp_currency'   => $this->group->currency->code,
            'filters'        => $this->staleFilters,
            'agents'         => Agent::where('group_id', $this->group->id)
                ->where('status', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Agent $agent) => ['code' => $agent->code, 'name' => $agent->name])
                ->all(),
            'aspos'          => $aspos->values()->all(),
            'purchase_orders' => $purchaseOrders->values()->all(),
        ];
    }

    private function getShoppingLists(): array
    {
        $rows = DB::table('shopping_list_items')
            ->join('supplier_products', 'supplier_products.id', '=', 'shopping_list_items.supplier_product_id')
            ->join('agents', 'agents.id', '=', 'shopping_list_items.agent_id')
            ->where('shopping_list_items.group_id', $this->group->id)
            ->where('shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('shopping_list_items.deleted_at')
            ->select([
                'shopping_list_items.id',
                'shopping_list_items.quantity_units',
                'shopping_list_items.agent_id',
                'agents.name as agent_name',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.cost',
            ])
            ->orderByDesc('shopping_list_items.created_at')
            ->get();

        $route = $this->dashboardRoute('grp.supply-chain.shopping_list.board');

        $withItems = [];
        foreach ($rows->groupBy('agent_id') as $agentRows) {
            $withItems[] = [
                'partner_name' => $agentRows->first()->agent_name,
                'count'        => $agentRows->count(),
                'total'        => round($agentRows->sum(fn ($row) => (float) $row->quantity_units * (float) $row->cost), 2),
                'currency'     => $this->group->currency->code,
                'items'        => $agentRows->take(10)->map(fn ($row) => [
                    'id'             => $row->id,
                    'quantity'       => $row->quantity_units,
                    'org_stock_code' => $row->code,
                    'org_stock_name' => $row->name,
                    'family_name'    => null,
                ])->values()->all(),
                'listRoute'    => $route,
            ];
        }

        $partnerRows = DB::table('partner_shopping_list_items as psli')
            ->join('org_stocks', 'org_stocks.id', '=', 'psli.org_stock_id')
            ->join('organisations as buyer', 'buyer.id', '=', 'psli.organisation_id')
            ->join('organisations as seller', 'seller.id', '=', 'psli.partner_organisation_id')
            ->where('psli.group_id', $this->group->id)
            ->where('psli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('psli.deleted_at')
            ->select([
                'psli.id',
                'psli.quantity',
                'psli.org_partner_id',
                'psli.organisation_id',
                'buyer.code as buyer_code',
                'buyer.slug as buyer_slug',
                'seller.name as seller_name',
                'org_stocks.code',
                'org_stocks.name',
            ])
            ->orderByDesc('psli.created_at')
            ->get();

        foreach ($partnerRows->groupBy('org_partner_id') as $partnerItems) {
            $first = $partnerItems->first();
            $withItems[] = [
                'partner_name' => $first->buyer_code.' → '.$first->seller_name,
                'count'        => $partnerItems->count(),
                'total'        => 0,
                'currency'     => $this->group->currency->code,
                'items'        => $partnerItems->take(10)->map(fn ($row) => [
                    'id'             => $row->id,
                    'quantity'       => $row->quantity,
                    'org_stock_code' => $row->code,
                    'org_stock_name' => $row->name,
                    'family_name'    => null,
                ])->values()->all(),
                'listRoute'    => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                    'parameters' => [
                        'organisation' => $first->buyer_slug,
                        'orgPartner'   => $first->org_partner_id,
                    ],
                ],
            ];
        }

        $empty = Agent::where('group_id', $this->group->id)
            ->where('status', true)
            ->whereNotIn('id', $rows->pluck('agent_id')->unique())
            ->orderBy('name')
            ->get()
            ->map(fn (Agent $agent) => [
                'name'  => $agent->name,
                'route' => $route,
            ])->all();

        return [
            'withItems' => $withItems,
            'empty'     => $empty,
        ];
    }

    private function dashboardCard(
        string $label,
        string $description,
        string $icon,
        ?int $value,
        string $tone,
        string $routeName,
        array $metrics = [],
        array $query = []
    ): array {
        return [
            'label'       => $label,
            'description' => $description,
            'icon'        => $icon,
            'value'       => $value,
            'tone'        => $tone,
            'route'       => $this->dashboardRoute($routeName, $query),
            'metrics'     => $metrics,
        ];
    }

    private function dashboardMetric(string $label, int $value, string $routeName, array $query = []): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'route' => $this->dashboardRoute($routeName, $query),
        ];
    }

    private function dashboardRoute(string $routeName, array $query = []): array
    {
        $parameters = [];

        if ($query !== []) {
            $parameters['_query'] = $query;
        }

        return [
            'name'       => $routeName,
            'parameters' => $parameters,
        ];
    }

    public function htmlResponse(): Response
    {
        return Inertia::render(
            'SupplyChain/SupplyChainDashboard',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(),
                'title'          => __('Supply chain'),
                'pageHead'       => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-box-usd'],
                        'title' => __('Supply chain'),
                    ],
                    'title' => __('Supply chain'),
                ],
                'dashboardCards' => $this->getDashboardCards(),
                'shoppingLists'  => Inertia::defer(fn () => $this->getShoppingLists()),
                'staleOrders'    => Inertia::defer(fn () => $this->getStaleOrders()),
                'search_demand'  => Inertia::defer(fn () => GetSearchDemandOpportunities::run($this->group)),
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.supply-chain.dashboard',
                        ],
                        'label' => __('Supply chain'),
                    ],
                ],
            ]
        );
    }
}
