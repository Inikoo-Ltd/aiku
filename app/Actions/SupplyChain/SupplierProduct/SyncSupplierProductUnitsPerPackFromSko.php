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
     * A carton holds a number of SKOs, so when the SKO packing changes from $previousSkoPackedIn the
     * units per carton follow it and the SKOs per carton stay the same. Only a supplier product whose
     * pack matched the old SKO packing is rescaled: any other pack (Aurora's own package unit, a
     * first link) says nothing about how many SKOs a carton holds.
     */
    public function handle(SupplierProduct $supplierProduct, ?int $previousSkoPackedIn = null): SupplierProduct
    {
        $skoPackedIn = $supplierProduct->skoPackedIn();
        if ($skoPackedIn === null || $skoPackedIn === $supplierProduct->units_per_pack) {
            return $supplierProduct;
        }

        $modelData = ['units_per_pack' => $skoPackedIn];
        if ($previousSkoPackedIn && $supplierProduct->units_per_pack === $previousSkoPackedIn && $supplierProduct->units_per_carton > 0) {
            $modelData['units_per_carton'] = (int) round($supplierProduct->units_per_carton / $supplierProduct->units_per_pack * $skoPackedIn);
        }

        return UpdateSupplierProduct::make()->action($supplierProduct, $modelData, strict: false);
    }
}
