<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Brand\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Helpers\Brand;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexBrandsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:brands';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Brand::class, $reindex, $reset);
    }


}
