<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 09:25
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Ticks, or unticks, a product's compliance document as checked and OK for this order, from the Compliance check of
 * the order's stages. The tick is kept on the order in data.compliance_checks with who ticked it and when; a document
 * with a file counts as checked without a tick. Ticking every document does not complete compliance: that stays a
 * manual mark.
 */
class CheckPurchaseOrderComplianceDocument extends OrgAction
{
    private PurchaseOrder $purchaseOrder;

    private TradeUnit $tradeUnit;

    public function authorize(ActionRequest $request): bool
    {
        if ($request->user()->authTo("procurement.{$this->organisation->id}.edit")) {
            return true;
        }

        $agentOrganisationId = $this->purchaseOrder->isAgentOrder() ? $this->purchaseOrder->agent?->organisation_id : null;

        return $agentOrganisationId && $request->user()->authTo("procurement.$agentOrganisationId.edit");
    }

    public function handle(PurchaseOrder $purchaseOrder, TradeUnit $tradeUnit, array $modelData): PurchaseOrder
    {
        $data = $purchaseOrder->data ?? [];
        $key  = 'compliance_checks.'.$tradeUnit->id.'.'.$modelData['scope'];

        if ($modelData['checked']) {
            Arr::set($data, $key, [
                'user_id' => request()->user()?->id,
                'at'      => now()->toIso8601String(),
            ]);
        } else {
            Arr::forget($data, $key);
            if (empty(Arr::get($data, 'compliance_checks.'.$tradeUnit->id))) {
                Arr::forget($data, 'compliance_checks.'.$tradeUnit->id);
            }
        }

        $purchaseOrder->update(['data' => $data]);

        return $purchaseOrder;
    }

    public function rules(): array
    {
        return [
            'scope'   => ['required', Rule::in(array_map(fn (TradeAttachmentScopeEnum $scope) => $scope->value, GetPurchaseOrderCompliance::DOCUMENTS))],
            'checked' => ['required', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!GetPurchaseOrderCompliance::make()->tradeUnits($this->purchaseOrder)->contains('id', $this->tradeUnit->id)) {
            $validator->errors()->add('scope', __('This product is not on the order'));
        }
    }

    public function asController(PurchaseOrder $purchaseOrder, TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->tradeUnit     = $tradeUnit;
        $this->initialisation($purchaseOrder->organisation, $request);

        $this->handle($purchaseOrder, $tradeUnit, $this->validatedData);
    }
}
