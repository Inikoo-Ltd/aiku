<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Search;

use App\Actions\HydrateModel;
use App\Actions\SupplyChain\Supplier\WithSupplierCommand;
use App\Actions\Traits\WithScoutReindex;
use App\Models\SupplyChain\Supplier;

class ReindexSupplierSearch extends HydrateModel
{
    use WithSupplierCommand;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:suppliers';


    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Supplier::class, $reindex, $reset);
    }


}
