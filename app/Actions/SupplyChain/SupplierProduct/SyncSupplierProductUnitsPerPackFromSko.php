<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncSupplierProductUnitsPerPackFromSko
{
    use AsAction;

    /**
     * A carton holds a number of SKOs, so when the SKO packing changes the units per carton
     * follow it and the SKOs per carton stay the same.
     */
    public function handle(SupplierProduct $supplierProduct): SupplierProduct
    {
        $skoPackedIn = $supplierProduct->skoPackedIn();
        if ($skoPackedIn === null || $skoPackedIn === $supplierProduct->units_per_pack) {
            return $supplierProduct;
        }

        $modelData = ['units_per_pack' => $skoPackedIn];
        if ($supplierProduct->units_per_pack > 0 && $supplierProduct->units_per_carton > 0) {
            $modelData['units_per_carton'] = (int) round($supplierProduct->units_per_carton / $supplierProduct->units_per_pack * $skoPackedIn);
        }

        return UpdateSupplierProduct::make()->action($supplierProduct, $modelData, strict: false);
    }
}
