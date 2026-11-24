<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\Media\AttachAttachmentToModel;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Anyone working an order, the buyer or the agent, uploads a product's compliance document from it; the document is
 * kept on the product's trade unit, so it counts for every order of that product, and remembers the order it was
 * uploaded from.
 */
class AttachPurchaseOrderComplianceDocument extends OrgAction
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

    public function handle(PurchaseOrder $purchaseOrder, TradeUnit $tradeUnit, array $modelData): void
    {
        AttachAttachmentToModel::make()->action($tradeUnit, [
            'attachments' => $modelData['attachments'],
            'scope'       => $modelData['scope'],
            'data'        => [
                'purchase_order_id'        => $purchaseOrder->id,
                'purchase_order_reference' => $purchaseOrder->reference,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'attachments'   => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:50000'],
            'scope'         => ['required', Rule::in(array_map(fn (TradeAttachmentScopeEnum $scope) => $scope->value, GetPurchaseOrderCompliance::DOCUMENTS))],
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
