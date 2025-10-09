<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 08:48:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Billables\Charge;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ChargeResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Charge $charge */
        $charge = $this;

        $amount = Arr::get($charge->settings, 'amount', 0);

        return [
            'id'            => $charge->id,
            'slug'          => $charge->slug,
            'code'          => $charge->code,
            'name'          => $charge->name,
            'label'         => $charge->label,
            'description'   => $charge->description,
            'state'         => $charge->state,
            'created_at'    => $charge->created_at,
            'updated_at'    => $charge->updated_at,
            'amount'        => $amount,
            'currency_code' => $charge->shop->currency->code,
            'settings'      => $charge->settings
        ];
    }
}
