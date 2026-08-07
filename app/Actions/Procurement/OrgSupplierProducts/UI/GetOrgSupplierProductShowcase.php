<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductShowcase;
use App\Models\Procurement\OrgSupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;

    public function handle(OrgSupplierProduct $orgSupplierProduct): array
    {
        return array_merge(
            $this->getSupplierProductShowcase($orgSupplierProduct->supplierProduct, withSupplyChainLink: true),
            [
                'organisation' => [
                    'name'         => $orgSupplierProduct->organisation->name,
                    'code'         => $orgSupplierProduct->organisation->code,
                    'state'        => $orgSupplierProduct->state,
                    'is_available' => $orgSupplierProduct->is_available,
                ],
                'parties'      => array_values(array_filter([
                    $this->getOrgSupplierParty($orgSupplierProduct->orgSupplier),
                    $this->getOrgAgentParty($orgSupplierProduct->orgAgent),
                ])),
                'stats'        => $this->getProcurementStatsBoxes($orgSupplierProduct->stats),
            ]
        );
    }
}
