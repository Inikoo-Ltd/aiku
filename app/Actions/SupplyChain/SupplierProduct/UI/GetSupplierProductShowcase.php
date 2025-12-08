<?php

/*
 * author Arya Permana - Kirin
 * created on 20-02-2025-11h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierProductShowcase
{
    use AsObject;

    public function handle(SupplierProduct $supplierProduct): array
    {
        $data = [
            'contactCard' => [
                'company' => $supplierProduct->supplier->name,
                'contact' => $supplierProduct->supplier->contact_name ?? '',
                'email' => $supplierProduct->supplier->email ?? '',
                'phone' => $supplierProduct->supplier->phone ?? '',
                'location' => $supplierProduct->supplier->location ?? '',
                // 'address'  => AddressResource::make($agent->organisation->address)->getArray(),
                'photo' => $supplierProduct->supplier->imageSources(),
            ],
            'stats' => [
                [
                    'label' => __('purchase orders'),
                    'count' => $supplierProduct->stats->number_purchase_orders,
                    'full' => true,
                ],
                [
                    'label' => __('deliveries'),
                    'count' => $supplierProduct->stats->number_stock_deliveries,
                ],
            ],
        ];

        return $data;
    }
}
