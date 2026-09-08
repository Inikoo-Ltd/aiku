<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 01:01:23 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\SupplyChain\Supplier\UI\WithSupplierInfo;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgSupplier;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierShowcase
{
    use AsObject;
    use WithSupplierInfo;

    public function handle(OrgSupplier $orgSupplier): array
    {
        $supplier = $orgSupplier->supplier;

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
                'photo'      => $supplier->imageSources(320, 320),
                'supplierInfo' => $this->supplierInfo($supplier),
            ],
            'stats'       => [
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $orgSupplier->stats->number_current_org_supplier_products,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                        'parameters' => [$orgSupplier->organisation->slug, $orgSupplier->slug],
                    ],
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $orgSupplier->stats->number_purchase_orders,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.purchase_orders.index',
                        'parameters' => [$orgSupplier->organisation->slug, $orgSupplier->slug],
                    ],
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $orgSupplier->stats->number_stock_deliveries,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
                        'parameters' => [$orgSupplier->organisation->slug, $orgSupplier->slug],
                    ],
                ],
            ],
        ];
    }
}
