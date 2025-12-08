<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 14:47:08 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncSupplierProductTradeUnits
{
    use AsAction;

    public function handle(SupplierProduct $supplierProduct, array $tradeUnitsData): SupplierProduct
    {

        $supplierProduct->tradeUnits()->sync($tradeUnitsData);

        return $supplierProduct;
    }
}
