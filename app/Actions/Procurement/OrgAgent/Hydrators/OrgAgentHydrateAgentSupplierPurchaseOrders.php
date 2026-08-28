<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\Hydrators;

use App\Models\Procurement\OrgAgent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgAgentHydrateAgentSupplierPurchaseOrders implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgAgent $orgAgent): string
    {
        return $orgAgent->id;
    }

    public function handle(OrgAgent $orgAgent): void
    {
        $stats = [
            'number_agent_supplier_purchase_orders' => $orgAgent->purchaseOrders()
                ->join('agent_supplier_purchase_orders', 'agent_supplier_purchase_orders.purchase_order_id', 'purchase_orders.id')
                ->whereNull('agent_supplier_purchase_orders.deleted_at')
                ->count(),
        ];

        $orgAgent->stats()->update($stats);
    }
}
