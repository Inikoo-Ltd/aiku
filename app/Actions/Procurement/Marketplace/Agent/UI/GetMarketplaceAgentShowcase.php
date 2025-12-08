<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Marketplace\Agent\UI;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMarketplaceAgentShowcase
{
    use AsObject;

    public function handle(Agent $agent): array
    {
        return [
            'contactCard' => [
                'company' => $agent->company_name,
                'contact' => $agent->contact_name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'address' => AddressResource::make($agent->getAddress('contact'))->getArray(),
                'photo' => $agent->getPhoto(),
            ],
            'stats' => [
                [
                    'label' => __('suppliers'),
                    'count' => $agent->stats->number_suppliers,
                ],
                [
                    'label' => __('products'),
                    'count' => $agent->stats->number_supplier_products,
                ],
                [
                    'label' => __('purchase orders'),
                    'count' => $agent->stats->number_purchase_orders,
                ],
                [
                    'label' => __('deliveries'),
                    'count' => $agent->stats->number_stock_deliveries,
                ],

            ],
        ];
    }
}
