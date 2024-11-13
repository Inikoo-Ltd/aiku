<?php
/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-10h-35m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrderStateToCancelled extends OrgAction
{
    use WithActionUpdate;
    use AsAction;
    use HasPurchaseOrderHydrators;


    /**
     * @var \App\Models\Procurement\PurchaseOrder
     */
    private PurchaseOrder $purchaseOrder;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $data = [
            'state' => PurchaseOrderStateEnum::CANCELLED
        ];

        $purchaseOrder->purchaseOrderTransactions()->update($data);

        $data['cancelled_at'] = now();

        $purchaseOrder = $this->update($purchaseOrder, $data);

        $this->purchaseOrderHydrate($purchaseOrder);

        return $purchaseOrder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("procurement.{$this->organisation->id}.edit");
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction      = true;
        $this->purchaseOrder = $purchaseOrder;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }


    public function asController(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
