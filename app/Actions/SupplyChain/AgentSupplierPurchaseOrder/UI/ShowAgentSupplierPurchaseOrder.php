<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Models\Helpers\Currency;
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

    private const DELIVERY_DONE_STATES = [
        AgentSupplierPurchaseOrderDeliveryStateEnum::RECEIVED,
        AgentSupplierPurchaseOrderDeliveryStateEnum::CHECKED,
        AgentSupplierPurchaseOrderDeliveryStateEnum::PLACED,
        AgentSupplierPurchaseOrderDeliveryStateEnum::CANCELLED,
    ];

    public function htmlResponse(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, ActionRequest $request): Response
    {
        $supplier      = $agentSupplierPurchaseOrder->supplier;
        $purchaseOrder = $agentSupplierPurchaseOrder->purchaseOrder;

        $isOverdue = $agentSupplierPurchaseOrder->estimated_received_at
            && $agentSupplierPurchaseOrder->estimated_received_at->isPast()
            && !in_array($agentSupplierPurchaseOrder->delivery_state, self::DELIVERY_DONE_STATES, true);

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
                    'title'   => $agentSupplierPurchaseOrder->reference,
                    'actions' => $this->canEdit ? [
                        [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'tooltip' => __('Edit agent supplier purchase order'),
                            'label'   => __('Edit'),
                            'route'   => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ] : [],
                ],
                'showcase'    => [
                    'reference'             => $agentSupplierPurchaseOrder->reference,
                    'state'                 => $agentSupplierPurchaseOrder->state->labels()[$agentSupplierPurchaseOrder->state->value],
                    'delivery_state'        => $agentSupplierPurchaseOrder->delivery_state->value,
                    'date'                  => $agentSupplierPurchaseOrder->date,
                    'confirmed_at'          => $agentSupplierPurchaseOrder->confirmed_at,
                    'cancelled_at'          => $agentSupplierPurchaseOrder->cancelled_at,
                    'cost_total'            => $agentSupplierPurchaseOrder->cost_total,
                    'currency_code'         => $agentSupplierPurchaseOrder->currency->code,
                    'notes'                 => $agentSupplierPurchaseOrder->notes,
                    'deposit_amount'        => $agentSupplierPurchaseOrder->deposit_amount,
                    'deposit_paid_at'       => $agentSupplierPurchaseOrder->deposit_paid_at,
                    'balance_paid_at'       => $agentSupplierPurchaseOrder->balance_paid_at,
                    'estimated_received_at' => $agentSupplierPurchaseOrder->estimated_received_at,
                    'is_overdue'            => $isOverdue,
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
                'deposits'    => $this->getDeposits($agentSupplierPurchaseOrder),
            ]
        );
    }

    private function getDeposits(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder): array
    {
        $deposits = $agentSupplierPurchaseOrder->deposits()->orderBy('id')->get();

        return [
            'can_edit' => $this->canEdit,
            'list'     => $deposits->map(fn ($deposit) => [
                'id'                  => $deposit->id,
                'reference'           => $deposit->reference,
                'amount'              => $deposit->amount,
                'currency_id'         => $deposit->currency_id,
                'currency_code'       => $deposit->currency->code,
                'state'               => $deposit->state->value,
                'state_label'         => $deposit->state->labels()[$deposit->state->value],
                'paid_to_supplier_at' => $deposit->paid_to_supplier_at,
                'unapplied_amount'    => $deposit->unapplied_amount,
                'notes'               => $deposit->notes,
                'applications'        => $this->getApplicationHistory($deposit),
                'updateRoute'         => [
                    'name'       => 'grp.models.aspo-deposit.update',
                    'parameters' => ['aspoDeposit' => $deposit->id],
                    'method'     => 'patch',
                ],
                'stateRoute'          => [
                    'name'       => 'grp.models.aspo-deposit.state',
                    'parameters' => ['aspoDeposit' => $deposit->id],
                    'method'     => 'patch',
                ],
            ])->all(),
            'storeRoute' => [
                'name'       => 'grp.models.agent_supplier_purchase_order.deposit.store',
                'parameters' => ['agentSupplierPurchaseOrder' => $agentSupplierPurchaseOrder->id],
                'method'     => 'post',
            ],
            'currency_code' => $agentSupplierPurchaseOrder->currency->code,
            'currency_id'   => $agentSupplierPurchaseOrder->currency_id,
            'currencies'    => Currency::orderBy('code')->get(['id', 'code'])->toArray(),
        ];
    }

    private function getApplicationHistory($deposit): array
    {
        return $deposit->stockDeliveryApplications()
            ->withTrashed()
            ->with(['stockDelivery', 'createdBy', 'deletedBy'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($application) => [
                'id'                    => $application->id,
                'amount'                => $application->amount,
                'stock_delivery_reference' => $application->stockDelivery?->reference,
                'created_by_name'       => $application->createdBy?->contact_name ?? $application->createdBy?->username,
                'created_at'            => $application->created_at,
                'is_removed'            => $application->trashed(),
                'deleted_by_name'       => $application->deletedBy?->contact_name ?? $application->deletedBy?->username,
                'deleted_at'            => $application->deleted_at,
                'deleteRoute'           => !$application->trashed() && $this->canEdit ? [
                    'name'       => 'grp.models.stock-delivery-deposit-application.delete',
                    'parameters' => ['stockDeliveryDepositApplication' => $application->id],
                    'method'     => 'delete',
                ] : null,
            ])->all();
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
