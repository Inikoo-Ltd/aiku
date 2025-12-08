<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Marketplace\Supplier\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Supplier;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMarketplaceSupplierShowcase
{
    use AsObject;

    public function handle(Supplier $supplier): array
    {
        return [
            'contactCard' => [
                'contact' => $supplier->contact_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => AddressResource::make($supplier->address)->getArray(),
                'photo' => $supplier->getPhoto(),
            ],
            'stats' => [
                [
                    'label' => __('products'),
                    'count' => $supplier->stats->number_supplier_products,
                ],
                [
                    'label' => __('purchase orders'),
                    'count' => $supplier->stats->number_purchase_orders,
                ],
                [
                    'label' => __('deliveries'),
                    'count' => $supplier->stats->number_stock_deliveries,
                ],

            ],
        ];
    }
}
