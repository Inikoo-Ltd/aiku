<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dispatching\Shipper\Json;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\ShippersResource;
use App\Models\Dispatching\Shipper;

class GetShippers extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);

        return Shipper::where('organisation_id', $organisation->id)
            ->where('status', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    public function jsonResponse($shipper)
    {
        return ShippersResource::collection($shipper);
    }
}
