<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\AgentLabel;

use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;

trait WithAgentOrgStockBarcodeLabel
{
    /**
     * An agent holds no SKO of its own, so it prints the barcode label of a SKO it buys for us, never of any other.
     */
    protected function ensureAgentBuysOrgStock(Organisation $organisation, OrgStock $orgStock): void
    {
        $agent = $this->getOrganisationAgent($organisation);

        abort_unless(
            $agent && GetAgentOrgStocks::run($agent, orgStockId: $orgStock->id)->where('org_stocks.id', $orgStock->id)->exists(),
            404
        );
    }
}
