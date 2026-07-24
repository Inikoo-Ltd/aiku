<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Procurement\PurchaseOrder\Traits\HasPurchaseOrderHydrators;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePurchaseOrdersDeliveryStateFromStockDelivery
{
    use AsAction;
    use HasPurchaseOrderHydrators;

    public function handle(StockDelivery $stockDelivery): void
    {
        $deliveryState = PurchaseOrderDeliveryStateEnum::tryFrom($stockDelivery->state->value);

        if ($deliveryState === null) {
            return;
        }

        foreach ($stockDelivery->purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->delivery_state === $deliveryState) {
                continue;
            }

            $purchaseOrder->update(['delivery_state' => $deliveryState]);

            $this->purchaseOrderHydrate($purchaseOrder);
        }
    }

    public function action(StockDelivery $stockDelivery): void
    {
        $this->handle($stockDelivery);
    }
}
