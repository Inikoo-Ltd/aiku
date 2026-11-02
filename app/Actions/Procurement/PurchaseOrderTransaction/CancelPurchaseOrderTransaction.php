<?php

namespace App\Actions\Procurement\PurchaseOrderTransaction;

use App\Actions\Traits\Authorisations\WithPurchaseOrderAgentEditAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\NotifyBuyerOfAgentStageChange;
use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrderTransactionResource;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class CancelPurchaseOrderTransaction extends OrgAction
{
    use WithPurchaseOrderAgentEditAuthorisation;
    use WithActionUpdate;

    public function handle(PurchaseOrderTransaction $purchaseOrderTransaction): PurchaseOrderTransaction
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        $purchaseOrderTransaction = DB::transaction(function () use ($purchaseOrder, $purchaseOrderTransaction) {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->first();
            $purchaseOrderTransaction->refresh();

            if ($purchaseOrder->areLinesLockedByProduction()) {
                abort(422, __('The supplier is already making this order: its items can no longer be cancelled'));
            }

            if ($purchaseOrderTransaction->state === PurchaseOrderTransactionStateEnum::CONFIRMED) {
                $isOnDelivery = $purchaseOrder->purchaseOrderTransactions()
                    ->whereKey($purchaseOrderTransaction->id)
                    ->whereExists(fn (Builder $items) => StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction($items))
                    ->exists();

                if ($isOnDelivery) {
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

            return $purchaseOrderTransaction;
        });

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrderTransaction->purchaseOrder)->delay($this->hydratorsDelay);

        return $purchaseOrderTransaction;
    }

    public function asController(PurchaseOrder $purchaseOrder, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): PurchaseOrderTransaction
    {
        abort_unless($purchaseOrderTransaction->purchase_order_id === $purchaseOrder->id, 404);
        $this->initialisation($purchaseOrderTransaction->organisation, $request);

        $purchaseOrderTransaction = $this->handle($purchaseOrderTransaction);

        NotifyBuyerOfAgentStageChange::run($purchaseOrder, $request->user(), __('the agent cancelled :code', [
            'code' => $purchaseOrderTransaction->supplierProduct?->code ?? $purchaseOrderTransaction->orgStock?->code,
        ]));

        return $purchaseOrderTransaction;
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
