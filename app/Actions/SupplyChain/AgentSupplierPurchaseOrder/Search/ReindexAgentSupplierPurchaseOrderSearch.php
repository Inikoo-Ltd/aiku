<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexAgentSupplierPurchaseOrderSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:agent_supplier_purchase_orders';


    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(AgentSupplierPurchaseOrder::class, $reindex, $reset);
    }


}
