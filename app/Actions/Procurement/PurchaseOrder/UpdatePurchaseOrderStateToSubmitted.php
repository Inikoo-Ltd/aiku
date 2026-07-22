<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderStateToSubmitted extends OrgAction
{
    use WithActionUpdate;
    use AsAction;
    use HasPurchaseOrderHydrators;

    private PurchaseOrder $purchaseOrder;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS) {
            $validator->errors()->add('state', __('Purchase order can only be submitted if it is in process'));
        }

        if ($this->purchaseOrder->purchaseOrderTransactions()->count() === 0) {
            $validator->errors()->add('transactions', __('Purchase order must have at least one item to be submitted'));
        }
    }

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $purchaseOrder->purchaseOrderTransactions()->update([
            'state' => PurchaseOrderTransactionStateEnum::SUBMITTED,
        ]);

        $purchaseOrder = $this->update($purchaseOrder, [
            'state'        => PurchaseOrderStateEnum::SUBMITTED,
            'submitted_at' => now(),
        ]);

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);

        $this->purchaseOrderHydrate($purchaseOrder);

        return $purchaseOrder;
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
