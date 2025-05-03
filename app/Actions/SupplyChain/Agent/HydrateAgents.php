<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:16:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSupplierProducts;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Models\SupplyChain\Agent;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateAgents
{
    use asAction;

    public string $commandSignature = 'hydrate:agents';

    public function handle(Agent $agent): void
    {
        AgentHydrateSuppliers::run($agent);
        AgentHydrateSupplierProducts::run($agent);
    }

    public function asCommand(Command $command): int
    {

        $command->withProgressBar(Agent::all(), function (Agent $agent) {
            $this->handle($agent);
        });

        return 0;
    }
}
