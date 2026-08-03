<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexMasterProductCategoriesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:master_product_categories';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(MasterProductCategory::class, $reindex, $reset);
    }


}
