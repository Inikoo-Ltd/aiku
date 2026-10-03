<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\ActionRequest;

class CancelPurchaseOrderTransaction extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithActionUpdate;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        if ($purchaseOrderTransaction->state === PurchaseOrderTransactionStateEnum::CONFIRMED) {
            $isAwaitingDelivery = StoreStockDeliveryFromPurchaseOrder::transactionsAwaitingDelivery($purchaseOrder)
                ->whereKey($purchaseOrderTransaction->id)
                ->exists();

            if (!$isAwaitingDelivery) {
                abort(422, __('This item is on a stock delivery, deal with it there'));
            }
        } elseif ($purchaseOrderTransaction->state !== PurchaseOrderTransactionStateEnum::SUBMITTED) {
            abort(422, __('Only submitted or confirmed items can be cancelled'));
        }

        $purchaseOrderTransaction = $this->update($purchaseOrderTransaction, [
            'state'          => PurchaseOrderTransactionStateEnum::CANCELLED,
            'net_amount'     => 0,
            'grp_net_amount' => 0,
            'org_net_amount' => 0,
        ]);

        CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);

        if ($purchaseOrder->state === PurchaseOrderStateEnum::CONFIRMED
            && $purchaseOrder->stockDeliveries()->where('stock_deliveries.state', '!=', StockDeliveryStateEnum::CANCELLED)->exists()
            && !$purchaseOrder->purchaseOrderTransactions()->where('state', PurchaseOrderTransactionStateEnum::CONFIRMED)->exists()) {
            $purchaseOrder->update([
                'state'      => PurchaseOrderStateEnum::SETTLED,
                'settled_at' => now(),
            ]);
        }
        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrderTransaction;
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        return $this->handle($purchaseOrderTransaction);
    }

    public function action(PurchaseOrderTransaction $purchaseOrderTransaction, int $hydratorsDelay = 0): PurchaseOrderTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($purchaseOrderTransaction->organisation, []);

        return $this->handle($purchaseOrderTransaction);
    }

    public function jsonResponse(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransactionResource
    {
        return new PurchaseOrderTransactionResource($purchaseOrderTransaction);
    }
}
