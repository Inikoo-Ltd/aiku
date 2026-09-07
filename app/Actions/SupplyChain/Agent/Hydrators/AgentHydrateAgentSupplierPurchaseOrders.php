<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Hydrators;

use App\Models\SupplyChain\Agent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AgentHydrateAgentSupplierPurchaseOrders implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Agent $agent): string
    {
        return $agent->id;
    }

    public function handle(Agent $agent): void
    {
        $stats = [
            'number_agent_supplier_purchase_orders' => $agent->suppliers()
                ->join('agent_supplier_purchase_orders', 'agent_supplier_purchase_orders.supplier_id', 'suppliers.id')
                ->whereNull('agent_supplier_purchase_orders.deleted_at')
                ->count(),
        ];

        $agent->stats()->update($stats);
    }
}
