<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Apr 2026 15:51:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexOrgStockSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:org_stocks';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(OrgStock::class, $reindex, $reset);
    }

}
