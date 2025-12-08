<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Models\Procurement\OrgSupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierProductShowcase
{
    use AsObject;

    public function handle(OrgSupplierProduct $orgSupplierProduct): array
    {
        $data = [
            'contactCard' => [
                'company' => $orgSupplierProduct->organisation->name,
                'contact' => $orgSupplierProduct->organisation->contact_name ?? '',
                'email' => $orgSupplierProduct->organisation->email ?? '',
                'phone' => $orgSupplierProduct->organisation->phone ?? '',
                'location' => $orgSupplierProduct->organisation->location ?? '',
                // 'address'  => AddressResource::make($agent->organisation->address)->getArray(),
                'photo' => $orgSupplierProduct->organisation->imageSources(),
            ],
            'stats' => [
                [
                    'label' => __('purchase orders'),
                    'count' => $orgSupplierProduct->stats->number_purchase_orders,
                    'full' => true,
                ],
                [
                    'label' => __('deliveries'),
                    'count' => $orgSupplierProduct->stats->number_stock_deliveries,
                ],
            ],
        ];

        return $data;
    }
}
