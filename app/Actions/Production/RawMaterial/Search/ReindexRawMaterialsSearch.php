<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Production\RawMaterial;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexRawMaterialsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:raw_materials';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(RawMaterial::class, $reindex, $reset);
    }
}
