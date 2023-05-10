<?php
/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 May 2023 11:47:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Supplier\Hydrators;

use App\Actions\WithTenantJob;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierDeliveriesHydrateItems implements ShouldBeUnique
{
    use AsAction;
    use WithTenantJob;

    public function handle(SupplierDelivery $supplierDelivery): void
    {
        $stats = [
            'number_of_items' => $supplierDelivery->items()->count()
        ];

        $supplierDelivery->update($stats);
    }

    public function getGrossWeight(PurchaseOrder $purchaseOrder): float
    {
        $grossWeight = 0;

        foreach ($purchaseOrder->items as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $grossWeight += $item->supplierProduct['grossWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $grossWeight;
    }

    public function getNetWeight(PurchaseOrder $purchaseOrder): float
    {
        $netWeight = 0;

        foreach ($purchaseOrder->items as $item) {
            foreach ($item->supplierProduct['tradeUnits'] as $tradeUnit) {
                $netWeight += $item->supplierProduct['netWeight'] * $tradeUnit->pivot->package_quantity;
            }
        }

        return $netWeight;
    }

    public function getTotalCostItem(PurchaseOrder $purchaseOrder): float
    {
        $costItems = 0;

        foreach ($purchaseOrder->items as $item) {
            $costItems += $item->unit_price * $item->supplierProduct['cost'];
        }

        return $costItems;
    }
}
