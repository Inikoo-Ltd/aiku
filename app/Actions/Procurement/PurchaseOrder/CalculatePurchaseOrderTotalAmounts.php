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
        $itemsNet = (float) $purchaseOrder->purchaseOrderTransactions()->sum('net_amount');

        $extras = (float) $purchaseOrder->cost_extra
            + (float) $purchaseOrder->cost_shipping
            + (float) $purchaseOrder->cost_duties
            + (float) $purchaseOrder->cost_tax;

        $purchaseOrder->update([
            'cost_items' => $itemsNet,
            'cost_total' => $itemsNet + $extras,
        ]);
    }
}
