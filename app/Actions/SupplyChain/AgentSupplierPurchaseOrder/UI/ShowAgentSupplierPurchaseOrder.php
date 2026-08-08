<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentSupplierPurchaseOrder extends OrgAction
{
    use WithSupplyChainAuthorisation;

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder): AgentSupplierPurchaseOrder
    {
        return $agentSupplierPurchaseOrder;
    }

    public function asController(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): AgentSupplierPurchaseOrder
    {
        $this->initialisationFromGroup(group(), $request);

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
                        'route' => [
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
        return array_merge(
            IndexAgentSupplierPurchaseOrders::make()->getBreadcrumbs(
                'grp.supply-chain.agent_supplier_purchase_orders.index',
                []
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
