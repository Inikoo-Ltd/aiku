<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\SupplyChain\Supplier\UI\WithSupplierInfo;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgAgentShowcase
{
    use AsObject;
    use WithSupplierInfo;
    use WithProcurementSerialReferences;

    public function handle(OrgAgent $orgAgent, Organisation $viewingOrganisation): array
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
                'currency'     => $organisation->currency,
                'address'      => AddressResource::make($organisation->address)->getArray(),
                'photo'        => $agent->imageSources(320, 320),
                'supplierInfo' => [
                    ...$this->supplierInfo($agent),
                    'next_purchase_order_reference' => $this->nextPurchaseOrderReference($orgAgent),
                    'next_stock_delivery_reference' => $this->nextStockDeliveryReference($orgAgent),
                ],
            ],
        ];
    }
}
