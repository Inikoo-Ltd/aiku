<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 10:12:27 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetSearchDemandOpportunities;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowSupplyChainDashboard extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use AsAction;
    use WithInertia;

    public function asController(ActionRequest $request): void
    {
        $this->initialisationFromGroup(app('group'), $request);
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
                'breadcrumbs'    => array_merge($this->getBreadcrumbs(), [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => ['name' => 'grp.supply-chain.overview'],
                            'label' => __('Overview'),
                        ],
                    ],
                ]),
                'title'          => __('Supply chain overview'),
                'pageHead'       => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Supply chain overview'),
                    ],
                    'title' => __('Overview'),
                ],
                'dashboardCards' => $this->getDashboardCards(),
                'shoppingLists'  => Inertia::defer(fn () => $this->getShoppingLists()),
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
