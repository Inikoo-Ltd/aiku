<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 17:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\GetAgentSupplierPerformance;
use App\Actions\Procurement\OrgAgent\GetAgentLeadTimes;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Where everything ordered through an agent is, from the shopping list to the containers being
 * booked in, and which of the agent's sub-suppliers are holding it up.
 */
class ShowAgentOrderPipeline extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;

    private OrgAgent $orgAgent;

    public function handle(OrgAgent $orgAgent): array
    {
        $dashboard = ShowAgentShoppingDashboard::make();

        $shoppingList = DB::table('shopping_list_items')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as total, min(created_at) as oldest_at')
            ->first();

        return [
            'shopping_list'           => [
                'open_items_count' => (int) $shoppingList->total,
                'oldest_item_at'   => $shoppingList->oldest_at,
            ],
            'lead_time'               => GetAgentLeadTimes::run($orgAgent)['agent'],
            'suppliers'               => GetAgentSupplierPerformance::run($orgAgent),
            'open_agent_purchase_orders' => $dashboard->openAgentPurchaseOrders($orgAgent),
            'open_stock_deliveries'   => $dashboard->openStockDeliveries($orgAgent),
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): array
    {
        abort_unless($orgAgent->organisation_id === $organisation->id, 404);
        $this->orgAgent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/AgentOrderPipeline',
            [
                'breadcrumbs'             => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'                   => __('Order pipeline'),
                'pageHead'                => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-clipboard-list'],
                        'title' => __('Order pipeline'),
                    ],
                    'model'         => $this->orgAgent->agent->name,
                    'title'         => __('Order pipeline'),
                    'subNavigation' => $this->getOrgAgentNavigation($this->orgAgent),
                ],
                'shoppingList'            => $data['shopping_list'],
                'leadTime'                => $data['lead_time'],
                'suppliers'               => $data['suppliers'],
                'openAgentPurchaseOrders' => $data['open_agent_purchase_orders'],
                'openStockDeliveries'     => $data['open_stock_deliveries'],
                'shoppingListRoute'       => [
                    'name'       => 'grp.org.procurement.shopping_list.index',
                    'parameters' => [$this->organisation->slug],
                ],
                'stockDeliveriesRoute'    => [
                    'name'       => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                    'parameters' => [$this->organisation->slug, $this->orgAgent->slug],
                ],
                'agentPurchaseOrdersRoute' => [
                    'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.index',
                    'parameters' => [$this->organisation->slug, $this->orgAgent->slug],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowOrgAgent::make()->getBreadcrumbs('grp.org.procurement.org_agents.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.show.order_pipeline',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Order pipeline'),
                        'icon'  => 'fal fa-clipboard-list',
                    ],
                ],
            ]
        );
    }
}
