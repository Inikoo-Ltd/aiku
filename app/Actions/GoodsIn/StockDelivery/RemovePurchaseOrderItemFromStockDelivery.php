<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-22 10:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateItems;
use App\Actions\GoodsIn\StockDelivery\Traits\HasStockDeliveryHydrators;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateDeliveryState;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Takes one purchase order item out of a container in process, its units going back to awaiting delivery. Once none
 * of the order's items is left in the container the order leaves it too.
 */
class RemovePurchaseOrderItemFromStockDelivery extends OrgAction
{
    use HasStockDeliveryHydrators;

    private StockDelivery $stockDelivery;
    private PurchaseOrderTransaction $purchaseOrderTransaction;

    public static function itemsOfTransaction(StockDelivery $stockDelivery, PurchaseOrderTransaction $purchaseOrderTransaction): HasMany
    {
        return $stockDelivery->items()
            ->whereRaw("(stock_delivery_items.data->>'purchase_order_transaction_id')::bigint = ?", [$purchaseOrderTransaction->id])
            ->whereNotIn('stock_delivery_items.state', [StockDeliveryItemStateEnum::CANCELLED, StockDeliveryItemStateEnum::NOT_RECEIVED]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return AddPurchaseOrdersToStockDelivery::userCanAdd($this->stockDelivery, $request->user());
    }

    public function afterValidator(Validator $validator): void
    {
        if (!RemovePurchaseOrderFromStockDelivery::acceptsRemovals($this->stockDelivery)) {
            $validator->errors()->add('purchase_order_item', __('Items can only be removed from a container in process'));
        } elseif (!self::itemsOfTransaction($this->stockDelivery, $this->purchaseOrderTransaction)->exists()) {
            $validator->errors()->add('purchase_order_item', __('This item is not in this container'));
        }
    }

    public function handle(StockDelivery $stockDelivery, PurchaseOrderTransaction $purchaseOrderTransaction): StockDelivery
    {
        $purchaseOrder = $purchaseOrderTransaction->purchaseOrder;

        $isPurchaseOrderLeft = DB::transaction(function () use ($stockDelivery, $purchaseOrderTransaction, $purchaseOrder) {
            StockDelivery::whereKey($stockDelivery->id)->lockForUpdate()->first();

            self::itemsOfTransaction($stockDelivery, $purchaseOrderTransaction)->get()
                ->each(fn (StockDeliveryItem $stockDeliveryItem) => $stockDeliveryItem->delete());

            return RemovePurchaseOrderFromStockDelivery::itemsOfPurchaseOrder($stockDelivery, $purchaseOrder)->doesntExist();
        });

        if ($isPurchaseOrderLeft) {
            return RemovePurchaseOrderFromStockDelivery::make()->action($stockDelivery, $purchaseOrder);
        }

        PurchaseOrderHydrateTransactions::dispatch($purchaseOrder);
        PurchaseOrderHydrateDeliveryState::run($purchaseOrder);
        StockDeliveriesHydrateItems::run($stockDelivery);
        $this->runStockDeliveryHydrators($stockDelivery);

        return $stockDelivery->refresh();
    }

    public function asController(StockDelivery $stockDelivery, PurchaseOrderTransaction $purchaseOrderTransaction, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery            = $stockDelivery;
        $this->purchaseOrderTransaction = $purchaseOrderTransaction;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $purchaseOrderTransaction);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
