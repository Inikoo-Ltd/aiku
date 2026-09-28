<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\PreOrder;

use App\Enums\Ordering\PreOrder\PreOrderStateEnum;
use App\Models\Ordering\PreOrder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The buying team placed the supplier order for these pre-orders: from now on a trade customer
 * cancelling a made-to-order item loses the deposit.
 */
class MarkPreOrdersSupplierOrdered
{
    use AsObject;

    /**
     * @param  array<int, int>  $preOrderIds
     */
    public function handle(array $preOrderIds): int
    {
        return PreOrder::whereIn('id', $preOrderIds)
            ->where('state', PreOrderStateEnum::WAITING_FOR_GOODS)
            ->whereNull('supplier_ordered_at')
            ->update(['supplier_ordered_at' => now()]);
    }
}
