<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexAgentSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:agents';


    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Agent::class, $reindex, $reset);
    }


}
