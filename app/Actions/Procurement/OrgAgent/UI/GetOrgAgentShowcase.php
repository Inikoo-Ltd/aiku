<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\SupplyChain\Supplier\UI\WithSupplierInfo;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgAgent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgAgentShowcase
{
    use AsObject;
    use WithSupplierInfo;

    public function handle(OrgAgent $orgAgent): array
    {
        $agent        = $orgAgent->agent;
        $organisation = $agent->organisation;

        return [
            'contactCard' => [
                'created_at'   => $agent->created_at,
                'company'      => $organisation->name,
                'contact'      => $organisation->contact_name,
                'location'     => $organisation->location,
                'email'        => $organisation->email,
                'phone'        => $organisation->phone,
                'currency'     => $agent->currency ?? $organisation->currency,
                'address'      => AddressResource::make($organisation->address)->getArray(),
                'photo'        => $agent->imageSources(320, 320),
                'supplierInfo' => $this->supplierInfo($agent),
            ],
            'stats'       => [
                [
                    'label' => __('Suppliers'),
                    'icon'  => 'fal fa-person-dolly',
                    'count' => $orgAgent->stats->number_active_org_suppliers,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_agents.show.suppliers.index',
                        'parameters' => [$organisation->slug, $orgAgent->slug],
                    ],
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-box-usd',
                    'count' => $orgAgent->stats->number_current_org_supplier_products,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                        'parameters' => [$organisation->slug, $orgAgent->slug],
                    ],
                ],
                [
                    'label' => __('Purchase Orders'),
                    'icon'  => 'fal fa-clipboard-list',
                    'count' => $orgAgent->stats->number_purchase_orders,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_agents.show.agent_supplier_purchase_orders.index',
                        'parameters' => [$organisation->slug, $orgAgent->slug],
                    ],
                ],
                [
                    'label' => __('Deliveries'),
                    'icon'  => 'fal fa-truck-container',
                    'count' => $orgAgent->stats->number_stock_deliveries,
                    'route' => [
                        'name'       => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                        'parameters' => [$organisation->slug, $orgAgent->slug],
                    ],
                ],
            ],
        ];
    }
}
