<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexArtefactsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:artefacts';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Artefact::class, $reindex, $reset);
    }
}
