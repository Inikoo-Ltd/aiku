<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Procurement\PurchaseOrder;

class UpdatePurchaseOrderTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData): PurchaseOrderTransaction
    {
        $purchaseOrderTransaction = $this->update($purchaseOrderTransaction, $modelData, ['data']);
        CalculatePurchaseOrderTotalAmounts::run($purchaseOrderTransaction->purchaseOrder);

        return $purchaseOrderTransaction;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered' => ['sometimes', 'numeric', 'min:0'],
            'unit_quantity'    => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'unit_price'       => ['sometimes', 'nullable', 'numeric'],
        ];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurchaseOrderTransaction
    {
        $this->asAction = true;
        $this->strict   = $strict;

        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrderTransaction->organisation, $modelData);

        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }


    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        return $this->handle($purchaseOrderTransaction, $this->validatedData);
    }

    public function jsonResponse(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrderTransaction);
    }
}
