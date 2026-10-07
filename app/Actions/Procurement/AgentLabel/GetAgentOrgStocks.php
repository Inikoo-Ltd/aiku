<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel;

use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SupplyChain\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The SKOs an agent buys for us, in every organisation it buys for: the primary SKO of each product it
 * still supplies. Agents see these and nothing else.
 */
class GetAgentOrgStocks
{
    use AsObject;

    /**
     * @return Builder<OrgStock>
     */
    public function handle(Agent $agent): Builder
    {
        return OrgStock::query()->whereIn(
            'org_stocks.id',
            DB::table('org_supplier_products as osp')
                ->join('org_agents', 'org_agents.id', 'osp.org_agent_id')
                ->joinLateral(GetAgentStockCoverBuckets::bestOrgStock(), 'os')
                ->where('org_agents.agent_id', $agent->id)
                ->where('osp.state', '!=', OrgSupplierProductStateEnum::DISCONTINUED->value)
                ->select('os.id')
        );
    }
}
