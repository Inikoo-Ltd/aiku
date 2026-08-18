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
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
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
