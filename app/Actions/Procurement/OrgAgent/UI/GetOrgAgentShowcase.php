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
        $agent        = $orgAgent->agent;
        $organisation = $agent->organisation;

        return [
            'contactCard' => [
                'created_at' => $agent->created_at,
                'company'    => $organisation->name,
                'contact'    => $organisation->contact_name,
                'location'   => $organisation->location,
                'email'      => $organisation->email,
                'phone'      => $organisation->phone,
                'currency'   => $agent->currency ?? $organisation->currency,
                'address'    => AddressResource::make($organisation->address)->getArray(),
                'photo'      => $organisation->imageSources(),
            ],
            'stats'       => [
                [
                    'label' => __('Suppliers'),
                    'icon'  => 'fal fa-person-dolly',
                    'count' => $orgAgent->stats->number_active_org_suppliers,
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $orgAgent->stats->number_current_org_supplier_products,
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $orgAgent->stats->number_purchase_orders,
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $orgAgent->stats->number_stock_deliveries,
                ],
            ],
        ];
    }
}
