<?php

/*
 * author Arya Permana - Kirin
 * created on 28-11-2024-11h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder;

use App\Actions\GrpAction;
use App\Actions\Procurement\WithNoStrictProcurementOrderRules;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateAgentSupplierPurchaseOrder extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictProcurementOrderRules;
    use WithNoStrictRules;

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
                $this->strict ? 'alpha_dash' : 'string',
            ],
            'notes' => ['sometimes', 'string'],
        ];

        if ($this->strict) {
            $rules['reference'][] = new IUnique(
                table: 'agent_supplier_purchase_orders',
                extraConditions: [
                    [
                        'column' => 'supplier_id',
                        'value' => $this->agentSupplierPurchaseOrder->supplier_id,
                    ],
                    [
                        'column' => 'id',
                        'operator' => '!=',
                        'value' => $this->agentSupplierPurchaseOrder->id,
                    ],
                ]
            );
        }

        if (! $this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
            $rules = $this->noStrictProcurementOrderRules($rules);
            $rules = $this->noStrictPurchaseOrderDatesRules($rules);
        }

        return $rules;
    }

    public function action(AgentSupplierPurchaseOrder $agentSupplierPurchaseOrder, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): AgentSupplierPurchaseOrder
    {
        if (! $audit) {
            AgentSupplierPurchaseOrder::disableAuditing();
        }
        $this->asAction = true;
        $this->strict = $strict;
        $this->agentSupplierPurchaseOrder = $agentSupplierPurchaseOrder;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($agentSupplierPurchaseOrder->group, $modelData);

        return $this->handle($agentSupplierPurchaseOrder, $this->validatedData);
    }
}
