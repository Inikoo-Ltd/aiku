<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\JobOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexJobOrdersSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:job_orders';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(JobOrder::class, $reindex, $reset);
    }
}
