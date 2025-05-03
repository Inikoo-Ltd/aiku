<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Search;

use App\Models\SupplyChain\Agent;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexAgentSearch
{
    use AsAction;
    public string $commandSignature = 'search:agents';


    public function handle(Agent $agent): void
    {
        AgentRecordSearch::run($agent);
    }

    public function asCommand(Command $command): int
    {
        $command->withProgressBar(Agent::all(), function (Agent $agent) {
            $this->handle($agent);
        });

        return 0;
    }

}
