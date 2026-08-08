<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 May 2024 19:47:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierShowcase
{
    use AsObject;

    public function handle(Supplier $supplier): array
    {
        return [
            'contactCard' => [
                'created_at' => $supplier->created_at,
                'company'    => $supplier->company_name,
                'contact'    => $supplier->contact_name,
                'website'    => $supplier->contact_website,
                'location'   => $supplier->location,
                'email'      => $supplier->email,
                'phone'      => $supplier->phone,
                'currency'   => $supplier->currency,
                'address'    => AddressResource::make($supplier->address)->getArray(),
                'image_id'   => $supplier->image_id,
            ],
            'stats'       => [
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $supplier->stats->number_supplier_products,
                    'route' => [
                        'name'       => 'grp.supply-chain.suppliers.supplier_products.index',
                        'parameters' => [$supplier->slug],
                    ],
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $supplier->stats->number_purchase_orders,
                    'route' => [
                        'name'       => 'grp.supply-chain.suppliers.agent_supplier_purchase_orders.index',
                        'parameters' => [$supplier->slug],
                    ],
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $supplier->stats->number_stock_deliveries,
                ],
            ],
        ];
    }
}
