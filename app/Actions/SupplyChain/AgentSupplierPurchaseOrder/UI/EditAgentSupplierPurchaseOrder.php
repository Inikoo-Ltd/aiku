<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAgentSupplierPurchaseOrder extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        }

        return $request->user()->authTo('supply-chain.edit');
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
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit agent supplier purchase order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $agentSupplierPurchaseOrder,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $agentSupplierPurchaseOrder->reference,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Agent supplier purchase order'),
                            'icon'   => 'fal fa-clipboard-list',
                            'fields' => [
                                'deposit_amount' => [
                                    'type'  => 'input',
                                    'label' => __('Deposit amount'),
                                    'value' => $agentSupplierPurchaseOrder->deposit_amount,
                                ],
                                'deposit_paid_at' => [
                                    'type'  => 'date',
                                    'label' => __('Deposit paid'),
                                    'value' => $agentSupplierPurchaseOrder->deposit_paid_at,
                                ],
                                'balance_paid_at' => [
                                    'type'  => 'date',
                                    'label' => __('Balance paid'),
                                    'value' => $agentSupplierPurchaseOrder->balance_paid_at,
                                ],
                                'estimated_delivery_days' => [
                                    'type'  => 'input',
                                    'label' => __('Estimated delivery days'),
                                    'value' => $agentSupplierPurchaseOrder->estimated_delivery_days,
                                ],
                                'notes' => [
                                    'type'  => 'textarea',
                                    'label' => __('Notes'),
                                    'value' => $agentSupplierPurchaseOrder->notes,
                                ],
                            ]
                        ],
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.agent_supplier_purchase_order.update',
                            'parameters' => $agentSupplierPurchaseOrder->id
                        ],
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, string $routeName, array $routeParameters): array
    {
        return ShowAgentSupplierPurchaseOrder::make()->getBreadcrumbs(
            $agentSupplierPurchaseOrder,
            preg_replace('/edit$/', 'show', $routeName),
            $routeParameters,
            '('.__('Editing').')'
        );
    }
}
