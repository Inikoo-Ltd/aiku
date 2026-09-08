<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexManufactureTasksSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:manufacture_tasks';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ManufactureTask::class, $reindex, $reset);
    }
}
