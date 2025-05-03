<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:14:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSuppliers;
use App\Models\SupplyChain\Agent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AgentHydrateSuppliers implements ShouldBeUnique
{
    use AsAction;
    use WithHydrateSuppliers;

    public function getJobUniqueId(Agent $agent): string
    {
        return $agent->id;
    }

    public function handle(Agent $agent): void
    {
        $stats = $this->getSuppliersStats($agent);
        $agent->stats()->update($stats);
    }


}
