<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Aug 2024 22:13:51 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Search;

use App\Actions\HydrateModel;
use App\Actions\SupplyChain\Supplier\WithSupplierCommand;
use App\Models\SupplyChain\Supplier;

class ReindexSupplierSearch extends HydrateModel
{
    use WithSupplierCommand;

    public string $commandSignature = 'search:suppliers {--s|slugs=}';


    public function handle(Supplier $supplier): void
    {
        SupplierRecordSearch::run($supplier);
    }


}
