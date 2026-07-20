<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
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

        $currency = $agent->currency ?? $agent->organisation->currency;

        return [
            'code'       => $agent->code,
            'name'       => $agent->name,
            'slug'       => $agent->slug,
            'company'    => $agent->organisation->name,
            'location'   => $agent->organisation->location,
            'email'      => $agent->organisation->email,
            'phone'      => $agent->organisation->phone,
            'address'    => AddressResource::make($agent->organisation->address),
            'currency'   => $currency ? CurrencyResource::make($currency)->getArray() : null,
            'created_at' => $agent->created_at,
            'photo'      => $agent->organisation->imageSources()
        ];
    }
}
