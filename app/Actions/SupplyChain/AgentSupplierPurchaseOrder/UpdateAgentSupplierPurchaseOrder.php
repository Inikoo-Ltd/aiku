<?php

/*
 * author Arya Permana - Kirin
 * created on 28-11-2024-11h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderDeliveryStateEnum;
use App\Enums\SupplyChain\AgentSupplierPurchaseOrders\AgentSupplierPurchaseOrderStateEnum;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateAgentSupplierPurchaseOrder extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithNoStrictProcurementOrderRules;

    private AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder;

    public function handle(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, array $modelData): AgentSupplierPurchaseOrder
    {
        /** @var AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder */
        $agentSupplierPurchaseOrder = $this->update($agentSupplierPurchaseOrder, $modelData, ['data']);

        return $agentSupplierPurchaseOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference' => [
                'sometimes',
                'required',
                $this->strict ? 'alpha_dash' : 'string'
            ],
            'notes'          => ['sometimes', 'string'],
            'state'          => ['sometimes', 'required', Rule::enum(AgentSupplierPurchaseOrderStateEnum::class)],
            'delivery_state' => ['sometimes', 'required', Rule::enum(AgentSupplierPurchaseOrderDeliveryStateEnum::class)],
            'cost_items'     => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_shipping'  => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_total'     => ['sometimes', 'required', 'numeric', 'min:0'],
            'date'           => ['sometimes', 'required'],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'agent_supplier_purchase_orders',
                extraConditions: [
                    [
                        'column' => 'supplier_id',
                        'value'  => $this->agentSupplierPurchaseOrder->supplier_id,
                    ],
                    [
                        'column'   => 'id',
                        'operator' => '!=',
                        'value'    => $this->agentSupplierPurchaseOrder->id
                    ]
                ]
            );
        }


        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictPurchaseOrderDatesRules($rules);
        }

        return $rules;
    }

    public function action(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): AgentSupplierPurchaseOrder
    {
        if (!$audit) {
            AgentSupplierPurchaseOrder::disableAuditing();
        }
        $this->asAction                   = true;
        $this->strict                     = $strict;
        $this->agentSupplierPurchaseOrder = $agentSupplierPurchaseOrder;
        $this->hydratorsDelay             = $hydratorsDelay;
        $this->initialisationFromGroup($agentSupplierPurchaseOrder->group, $modelData);


        return $this->handle($agentSupplierPurchaseOrder, $this->validatedData);
    }
}
