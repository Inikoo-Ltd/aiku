<?php

/*
 * author Arya Permana - Kirin
 * created on 20-02-2025-11h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;

    public function handle(SupplierProduct $supplierProduct): array
    {
        return array_merge(
            $this->getSupplierProductShowcase($supplierProduct),
            [
                'parties' => array_values(array_filter([
                    $this->getSupplierParty($supplierProduct->supplier),
                    $this->getAgentParty($supplierProduct->agent),
                ])),
                'stats'   => $this->getProcurementStatsBoxes($supplierProduct->stats),
            ]
        );
    }
}
