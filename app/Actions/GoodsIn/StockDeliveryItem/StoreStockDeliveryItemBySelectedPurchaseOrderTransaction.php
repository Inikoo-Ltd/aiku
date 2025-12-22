<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryItem;

use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStockDeliveryItemBySelectedPurchaseOrderTransaction
{
    use AsAction;

    public function handle(StockDelivery $stockDelivery, array $purchaseOrderIds): array
    {
        $items                     = [];
        $purchaseOrderTransactions = PurchaseOrderTransaction::whereIn('purchase_order_id', $purchaseOrderIds)->get();

        foreach ($purchaseOrderTransactions as $item) {
            $items[] = StoreStockDeliveryItem::run($stockDelivery, $item->historicSupplierProduct, $item->orgStock, [
                'group_id'            => $item->group_id,
                'supplier_product_id' => $item->supplier_product_id,
                // 'unit_price'          => $item->net_amount,
                'unit_quantity'       => $item->quantity_ordered
            ]);
        }

        return $items;
    }
}
