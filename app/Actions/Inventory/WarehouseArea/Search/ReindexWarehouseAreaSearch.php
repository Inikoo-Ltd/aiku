<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Inventory\WarehouseArea;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexWarehouseAreaSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:warehouse_areas';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(WarehouseArea::class, $reindex, $reset);
    }

}
