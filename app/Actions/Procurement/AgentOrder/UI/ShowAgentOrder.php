<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent order as one order, the way staff knew the org-agent purchase order: every supplier's
 * lines in one place, products added from any of the agent's suppliers, submitted and sent to the
 * agent in one go. Each supplier's order stays the real order, linked from its block.
 */
class ShowAgentOrder extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgAgentSubNavigation;

    private OrgAgent $orgAgent;

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function handle(OrgAgent $orgAgent, string $agentOrderReference): Collection
    {
        return PurchaseOrder::inAgentOrder($orgAgent->organisation_id, $orgAgent->agent_id, $agentOrderReference)
            ->with(['currency', 'purchaseOrderTransactions.supplierProduct', 'purchaseOrderTransactions.orgStock'])
            ->orderBy('parent_code')
            ->get();
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, string $agentOrderReference, ActionRequest $request): Collection
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent, $agentOrderReference);
    }

    public function htmlResponse(Collection $purchaseOrders, ActionRequest $request): Response
    {
        $reference    = $request->route('agentOrderReference');
        $isOpen       = $purchaseOrders->every(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS);
        $canEditLines = $this->canEdit && $isOpen;
        $canSubmit    = $canEditLines && $purchaseOrders->contains(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->purchaseOrderTransactions->isNotEmpty());
        $state        = IndexAgentOrders::stateOf($purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->state->value)->unique()->values()->all());

        return Inertia::render(
            'Procurement/AgentOrder',
            [
                'title'       => __('Agent order').' '.$reference,
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead'    => [
                    'model'         => __('Agent order'),
                    'title'         => $reference,
                    'icon'          => ['icon' => ['fal', 'fa-boxes'], 'title' => __('Agent order')],
                    'afterTitle'    => ['label' => $state['label']],
                    'subNavigation' => $this->getOrgAgentNavigation($this->orgAgent),
                    'actions'       => array_values(array_filter([
                        $purchaseOrders->isNotEmpty() ? [
                            'type'   => 'button',
                            'style'  => 'tertiary',
                            'label'  => 'PDF',
                            'icon'   => 'fal fa-file-pdf',
                            'target' => '_blank',
                            'route'  => [
                                'name'       => 'grp.org.procurement.org_agents.show.agent_orders.pdf',
                                'parameters' => [$this->organisation->slug, $this->orgAgent->slug, $reference],
                            ],
                        ] : null,
                    ])),
                ],
                'agent_order' => [
                    'reference'              => $reference,
                    'state'                  => $state,
                    'agent'                  => [
                        'code' => $this->orgAgent->agent->code,
                        'name' => $this->orgAgent->agent->organisation->name,
                    ],
                    'number_supplier_orders' => $purchaseOrders->count(),
                    'number_items'           => $purchaseOrders->sum(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->purchaseOrderTransactions->count()),
                    'totals'                 => $purchaseOrders->groupBy(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->currency->code)
                        ->map(fn ($orders, $currency) => ['currency_code' => $currency, 'amount' => $orders->sum(fn (PurchaseOrder $purchaseOrder) => (float) $purchaseOrder->cost_total)])
                        ->values()->all(),
                    'org_total'              => [
                        'currency_code' => $this->organisation->currency->code,
                        'amount'        => $purchaseOrders->sum(fn (PurchaseOrder $purchaseOrder) => (float) $purchaseOrder->cost_total * (float) ($purchaseOrder->org_exchange ?: 1)),
                    ],
                    'is_open'                => $isOpen,
                    'can_edit'               => $canEditLines,
                ],
                'supplier_orders' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $this->supplierOrder($purchaseOrder, $canEditLines))->values()->all(),
                'products_list'   => $canEditLines ? [
                    'name'       => 'grp.json.org-agent.agent-order.org-supplier-products',
                    'parameters' => ['orgAgent' => $this->orgAgent->id, 'agentOrderReference' => $reference],
                ] : null,
                'submit'          => $canSubmit ? [
                    'route'    => [
                        'name'       => 'grp.models.org-agent.agent-order.submit',
                        'parameters' => ['orgAgent' => $this->orgAgent->id],
                        'method'     => 'patch',
                    ],
                    'reference' => $reference,
                    'channels'  => SendPurchaseOrderToSupplier::channels($purchaseOrders->first()),
                ] : null,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierOrder(PurchaseOrder $purchaseOrder, bool $canEditLines): array
    {
        return [
            'id'                    => $purchaseOrder->id,
            'reference'             => $purchaseOrder->reference,
            'supplier'              => ['code' => $purchaseOrder->parent_code, 'name' => $purchaseOrder->parent_name],
            'state'                 => $purchaseOrder->state->value,
            'state_label'           => $purchaseOrder->state->labels()[$purchaseOrder->state->value],
            'state_icon'            => $purchaseOrder->state->stateIcon()[$purchaseOrder->state->value],
            'delivery_state_label'  => $purchaseOrder->delivery_state->labels()[$purchaseOrder->delivery_state->value],
            'estimated_received_at' => $purchaseOrder->estimated_received_at,
            'approved_ready_at'     => $purchaseOrder->approved_ready_at,
            'deposit_amount'        => $purchaseOrder->deposit_amount,
            'deposit_paid_at'       => $purchaseOrder->deposit_paid_at,
            'currency_code'         => $purchaseOrder->currency->code,
            'cost_items'            => $purchaseOrder->cost_items,
            'cost_total'            => $purchaseOrder->cost_total,
            'route'                 => [
                'name'       => 'grp.org.procurement.purchase_orders.show',
                'parameters' => [$this->organisation->slug, $purchaseOrder->slug],
            ],
            'lines'                 => $purchaseOrder->purchaseOrderTransactions
                ->sortBy(fn (PurchaseOrderTransaction $transaction) => $transaction->supplierProduct?->code)
                ->map(fn (PurchaseOrderTransaction $transaction) => [
                    'id'               => $transaction->id,
                    'code'             => $transaction->supplierProduct?->code,
                    'name'             => $transaction->supplierProduct?->name,
                    'sko_code'         => $transaction->orgStock?->code,
                    'quantity_ordered' => (float) $transaction->quantity_ordered,
                    'units_per_carton' => $transaction->supplierProduct?->units_per_carton,
                    'unit_cost'        => $transaction->unit_cost,
                    'net_amount'       => $transaction->net_amount,
                    'state'            => $transaction->state->value,
                    'updateRoute'      => $canEditLines ? [
                        'name'       => 'grp.models.purchase-order.transaction.update',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id, 'purchaseOrderTransaction' => $transaction->id],
                        'method'     => 'patch',
                    ] : null,
                    'deleteRoute'      => $canEditLines ? [
                        'name'       => 'grp.models.purchase-order.transaction.delete',
                        'parameters' => ['purchaseOrder' => $purchaseOrder->id, 'purchaseOrderTransaction' => $transaction->id],
                        'method'     => 'delete',
                    ] : null,
                ])->values()->all(),
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexAgentOrders::make()->getBreadcrumbs(collect($routeParameters)->except('agentOrderReference')->all()),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => $routeParameters['agentOrderReference'],
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.show.agent_orders.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                ],
            ]
        );
    }
}
