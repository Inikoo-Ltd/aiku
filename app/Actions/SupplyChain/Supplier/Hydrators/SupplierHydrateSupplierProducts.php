<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:50:04 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSupplierProducts;
use App\Actions\Traits\WithEnumStats;
use App\Models\SupplyChain\Supplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierHydrateSupplierProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateSupplierProducts;

    public function getJobUniqueId(Supplier $supplier): string
    {
        return $supplier->id;
    }

    public function handle(Supplier $supplier): void
    {
        $stats = $this->getSupplierProductsStats($supplier);
        $supplier->stats()->update($stats);
    }


}
