<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentOrder;

use App\Actions\Procurement\PurchaseOrder\SendPurchaseOrderToSupplier;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class SendAgentOrderToAgent
{
    use AsAction;

    /**
     * @param  array<int, int>  $purchaseOrderIds
     */
    public function handle(array $purchaseOrderIds, string $agentOrderReference, string $channel): void
    {
        $purchaseOrders = PurchaseOrder::whereIn('id', $purchaseOrderIds)->orderBy('reference')->get();

        if ($purchaseOrders->isNotEmpty()) {
            SendPurchaseOrderToSupplier::make()->sendAgentOrder($purchaseOrders, $agentOrderReference, $channel);
        }
    }
}
