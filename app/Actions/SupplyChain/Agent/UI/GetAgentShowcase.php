<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentShowcase
{
    use AsObject;

    public function handle(Agent $agent): array
    {
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
                    'count' => $agent->stats->number_active_suppliers,
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $agent->stats->number_current_supplier_products,
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $agent->stats->number_purchase_orders,
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $agent->stats->number_stock_deliveries,
                ],
            ],
        ];
    }
}
