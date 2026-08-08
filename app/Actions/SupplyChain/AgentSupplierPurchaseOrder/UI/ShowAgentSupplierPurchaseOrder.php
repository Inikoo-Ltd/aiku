<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentSupplierPurchaseOrder extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

            return $request->user()->authTo("procurement.{$this->organisation->id}.view");
        }

        $this->canEdit = $request->user()->authTo("supply-chain.edit");

        return $request->user()->authTo("supply-chain.view");
    }

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder): AgentSupplierPurchaseOrder
    {
        return $agentSupplierPurchaseOrder;
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): AgentSupplierPurchaseOrder
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agentSupplierPurchaseOrder);
    }

    public function inOrganisation(Organisation $organisation, AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): AgentSupplierPurchaseOrder
    {
        $this->initialisation($organisation, $request);

        return $this->handle($agentSupplierPurchaseOrder);
    }

    public function htmlResponse(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): Response
    {
        $supplier      = $agentSupplierPurchaseOrder->supplier;
        $purchaseOrder = $agentSupplierPurchaseOrder->purchaseOrder;

        return Inertia::render(
            'SupplyChain/AgentSupplierPurchaseOrder',
            [
                'title'       => __('Agent supplier purchase order').' '.$agentSupplierPurchaseOrder->reference,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $agentSupplierPurchaseOrder,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'model' => __('Agent supplier purchase order'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-list'],
                        'title' => __('Agent supplier purchase order')
                    ],
                    'title' => $agentSupplierPurchaseOrder->reference,
                ],
                'showcase'    => [
                    'reference'      => $agentSupplierPurchaseOrder->reference,
                    'state'          => $agentSupplierPurchaseOrder->state->labels()[$agentSupplierPurchaseOrder->state->value],
                    'delivery_state' => $agentSupplierPurchaseOrder->delivery_state->value,
                    'date'           => $agentSupplierPurchaseOrder->date,
                    'confirmed_at'   => $agentSupplierPurchaseOrder->confirmed_at,
                    'cancelled_at'   => $agentSupplierPurchaseOrder->cancelled_at,
                    'cost_total'     => $agentSupplierPurchaseOrder->cost_total,
                    'currency_code'  => $agentSupplierPurchaseOrder->currency->code,
                    'notes'          => $agentSupplierPurchaseOrder->notes,
                    'supplier'       => $supplier ? [
                        'code'  => $supplier->code,
                        'name'  => $supplier->name,
                        'route' => str_starts_with($request->route()->getName(), 'grp.org.') ? null : [
                            'name'       => 'grp.supply-chain.suppliers.show',
                            'parameters' => [$supplier->slug]
                        ]
                    ] : null,
                    'purchase_order' => $purchaseOrder ? [
                        'reference' => $purchaseOrder->reference,
                    ] : null,
                    'number_transactions' => $agentSupplierPurchaseOrder->purchaseOrderTransactions()->count(),
                ],
            ]
        );
    }

    public function getBreadcrumbs(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $indexRouteName = str_starts_with($routeName, 'grp.org.')
            ? 'grp.org.procurement.agent_supplier_purchase_orders.index'
            : 'grp.supply-chain.agent_supplier_purchase_orders.index';

        return array_merge(
            IndexAgentSupplierPurchaseOrders::make()->getBreadcrumbs(
                $indexRouteName,
                Arr::only($routeParameters, 'organisation')
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => $agentSupplierPurchaseOrder->reference,
                    ],
                ],
            ]
        );
    }
}
