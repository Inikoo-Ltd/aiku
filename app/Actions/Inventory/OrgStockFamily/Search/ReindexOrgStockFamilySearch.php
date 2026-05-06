<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Inventory\OrgStockFamily\Search;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithScoutReindex;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexOrgStockFamilySearch extends HydrateModel
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:org_stock_families';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(OrgStockFamily::class, $reindex, $reset);
    }
}
