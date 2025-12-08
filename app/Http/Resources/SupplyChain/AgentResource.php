<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var Agent $agent */
        $agent = $this;

        return [
            'code' => $agent->code,
            'name' => $agent->name,
            'slug' => $agent->slug,
            'location' => $agent->organisation->location,
            'email' => $agent->organisation->email,
            'phone' => $agent->organisation->phone,
            'address' => AddressResource::make($agent->organisation->address),
            'photo' => $agent->organisation->imageSources(),
        ];
    }
}
