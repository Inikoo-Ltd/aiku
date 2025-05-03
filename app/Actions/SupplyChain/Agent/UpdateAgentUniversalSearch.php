<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 17:28:19 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\SupplyChain\Agent\Search\AgentRecordSearch;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAgentUniversalSearch
{
    use asAction;

    public string $commandSignature = 'agents:search';


    public function handle(Agent $agent): void
    {
        AgentRecordSearch::run($agent);
    }

    public function asCommand(): int
    {
        foreach (Agent::all() as $agent) {
            $this->handle($agent);
        }
        return 0;
    }
}
