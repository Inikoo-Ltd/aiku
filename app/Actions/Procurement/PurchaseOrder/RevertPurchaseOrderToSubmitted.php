<?php

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RevertPurchaseOrderToSubmitted extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use AsAction;
    use HasPurchaseOrderHydrators;
    use WithActionUpdate;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (!$purchaseOrder->state->isAgreed()) {
            abort(422, __('Only confirmed purchase orders can be reverted to submitted'));
        }

        if ($purchaseOrder->stockDeliveries()->exists()) {
            abort(422, __('Purchase order with stock deliveries cannot be reverted to submitted'));
        }

        $purchaseOrder->purchaseOrderTransactions()
            ->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)
            ->update([
                'state' => PurchaseOrderTransactionStateEnum::SUBMITTED,
            ]);

        $purchaseOrder = $this->update($purchaseOrder, [
            'state'        => PurchaseOrderStateEnum::SUBMITTED,
            'confirmed_at'          => null,
            'estimated_received_at' => $purchaseOrder->estimated_delivery_days !== null
                ? $purchaseOrder->submitted_at?->clone()->addDays($purchaseOrder->estimated_delivery_days)
                : null,
            'data'                  => [
                'estimated_production_date' => null,
                'estimated_receiving_date'  => null,
            ],
        ], ['data']);

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);

        $this->purchaseOrderHydrate($purchaseOrder);

        return $purchaseOrder;
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($purchaseOrder->organisation, $request);

        return $this->handle($purchaseOrder);
    }

    public function action(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        $this->asAction = true;
        $this->initialisation($purchaseOrder->organisation, []);

        return $this->handle($purchaseOrder);
    }

    public function jsonResponse(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($purchaseOrder);
    }
}
