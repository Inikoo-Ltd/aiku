<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\ArtefactFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexArtefactFamiliesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:artefact_families';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ArtefactFamily::class, $reindex, $reset);
    }
}
