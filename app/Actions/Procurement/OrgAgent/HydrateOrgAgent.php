<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:17:35 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateAgentSupplierPurchaseOrders;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSuppliers;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydratePurchaseOrders;
use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateStockDeliveries;
use App\Models\Procurement\OrgAgent;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateOrgAgent
{
    use asAction;

    public string $commandSignature = 'hydrate:org_agents';

    public function handle(OrgAgent $orgAgent): void
    {
        OrgAgentHydrateOrgSuppliers::run($orgAgent);
        OrgAgentHydratePurchaseOrders::run($orgAgent);
        OrgAgentHydrateOrgSupplierProducts::run($orgAgent);
        OrgAgentHydrateStockDeliveries::run($orgAgent);
        OrgAgentHydrateAgentSupplierPurchaseOrders::run($orgAgent);
    }

    public function asCommand(Command $command): int
    {

        $command->withProgressBar(OrgAgent::all(), function (OrgAgent $orgAgent) {
            $this->handle($orgAgent);
        });

        return 0;
    }
}
