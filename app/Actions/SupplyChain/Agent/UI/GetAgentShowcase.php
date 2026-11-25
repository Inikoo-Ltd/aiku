<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\SupplyChain\Supplier\UI\WithSupplierInfo;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentShowcase
{
    use AsObject;
    use WithSupplierInfo;

    public function handle(Agent $agent): array
    {
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
                'supplierInfo' => $this->supplierInfo($agent),
            ],
            'cleanHandover' => GetAgentCleanHandoverScore::run($agent),
        ];
    }
}
