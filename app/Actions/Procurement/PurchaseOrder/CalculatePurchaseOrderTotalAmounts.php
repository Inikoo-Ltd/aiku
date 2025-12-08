<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-13h-17m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Models\Procurement\PurchaseOrder;

class CalculatePurchaseOrderTotalAmounts extends OrgAction
{
    public function handle(PurchaseOrder $purchaseOrder): void
    {
        $items = $purchaseOrder->purchaseOrderTransactions()->get();
        $itemsNet = $items->sum('net_amount');

        data_set($modelData, 'cost_total', $itemsNet);
        data_set($modelData, 'cost_items', $itemsNet);
        data_set($modelData, 'cost_extra', 0);
        data_set($modelData, 'cost_shipping', 0);
        data_set($modelData, 'cost_duties', 0);
        data_set($modelData, 'cost_tax', 0);

        $purchaseOrder->update($modelData);
    }
}
