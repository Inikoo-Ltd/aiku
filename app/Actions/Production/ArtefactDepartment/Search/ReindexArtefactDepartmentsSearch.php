<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\ArtefactDepartment;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexArtefactDepartmentsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:artefact_departments';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ArtefactDepartment::class, $reindex, $reset);
    }
}
