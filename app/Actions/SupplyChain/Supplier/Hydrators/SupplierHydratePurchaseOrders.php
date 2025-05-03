<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:31:20 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Hydrators;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierHydratePurchaseOrders implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Supplier $supplier): string
    {
        return $supplier->id;
    }

    public function handle(Supplier $supplier): void
    {
        $stats = [
            'number_purchase_orders' => $supplier->purchaseOrders->count(),
        ];

        $purchaseOrderStateCounts = $supplier->purchaseOrders()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')->all();

        foreach (PurchaseOrderStateEnum::cases() as $productState) {
            $stats['number_purchase_orders_state_'.$productState->snake()] = Arr::get($purchaseOrderStateCounts, $productState->value, 0);
        }

        $supplier->stats()->update($stats);
    }


}
