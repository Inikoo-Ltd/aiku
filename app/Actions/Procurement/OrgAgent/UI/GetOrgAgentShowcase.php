<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgAgent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgAgentShowcase
{
    use AsObject;

    public function handle(OrgAgent $orgAgent): array
    {
        $agent = $orgAgent->agent;

        return [
            'contactCard' => [
                'company' => $agent->organisation->name,
                'contact' => $agent->organisation->contact_name,
                'email' => $agent->organisation->email,
                'phone' => $agent->organisation->phone,
                'location' => $agent->organisation->location,
                // 'address'  => AddressResource::make($agent->organisation->address)->getArray(),
                'photo' => $agent->organisation->imageSources(),
            ],
            'stats' => [
                [
                    'label' => __('Purchase Orders'),
                    'count' => $orgAgent->stats->number_purchase_orders,
                    'full' => true,
                ],
                [
                    'label' => __('Suppliers'),
                    'count' => $orgAgent->stats->number_org_suppliers,
                ],
                [
                    'label' => __('Products'),
                    'count' => $orgAgent->stats->number_org_supplier_products,
                ],
                [
                    'label' => __('Deliveries'),
                    'count' => $orgAgent->stats->number_stock_deliveries,
                    'full' => true,
                ],

            ],
        ];
    }
}
