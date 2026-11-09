<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\DepositRequest\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\DepositRequest\GetAgentMoneyRequestLines;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Where the agent builds a money request for one client: the wallet, the charges paid for each shipment, then
 * every open order with its advance ready ticked and its balance to tick when the goods are about to ship.
 */
class CreateAgentMoneyRequest extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    private Agent $agent;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        $clients = Organisation::whereIn('id', OrgAgent::where('agent_id', $agent->id)->select('organisation_id'))->orderBy('code')->get(['id', 'slug', 'code', 'name']);
        $client  = $clients->firstWhere('slug', $request->string('client')->value()) ?? $clients->first();
        abort_unless($client, 404);
        $purchaseOrder = PurchaseOrder::where('organisation_id', $client->id)->where('agent_id', $agent->id)->find($request->integer('purchase_order'));
        $due           = GetAgentMoneyRequestLines::run($agent, $client, $purchaseOrder?->id);
        $due['orders'] = array_map(fn (array $order) => [
            ...$order,
            'route'          => ['name' => 'grp.org.agent.purchase_orders.show', 'parameters' => ['organisation' => $organisation->slug, 'purchaseOrder' => $order['purchase_order_slug']]],
            'supplier_route' => $order['supplier_slug'] ? ['name' => 'grp.org.agent.sales.suppliers.show', 'parameters' => ['organisation' => $organisation->slug, 'supplier' => $order['supplier_slug']]] : null,
        ], $due['orders']);
        $due['shipments'] = array_map(fn (array $shipment) => [
            ...$shipment,
            'route' => ['name' => 'grp.org.agent.stock_deliveries.show', 'parameters' => ['organisation' => $organisation->slug, 'stockDelivery' => $shipment['stock_delivery_slug']]],
        ], $due['shipments']);

        return Inertia::render(
            'Org/Procurement/AgentMoneyRequestBuilder',
            [
                'title'       => __('New money request'),
                'breadcrumbs' => IndexAgentMoneyRequests::make()->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'icon'  => ['title' => __('Money requests'), 'icon' => 'fal fa-money-check-alt'],
                    'model' => __('Money request'),
                    'title' => __('New money request'),
                ],
                'clients'     => $clients->map(fn (Organisation $organisation) => ['slug' => $organisation->slug, 'label' => $organisation->code.' · '.$organisation->name])->values()->all(),
                'client'      => ['id' => $client->id, 'slug' => $client->slug, 'code' => $client->code],
                'purchase_order' => $purchaseOrder?->reference,
                'due'         => $due,
                'store_route' => ['name' => 'grp.models.agent.money_request.store', 'parameters' => ['agent' => $agent->id]],
                'advance_route_name' => 'grp.models.supplier.advance_percentage.update',
            ]
        );
    }
}
