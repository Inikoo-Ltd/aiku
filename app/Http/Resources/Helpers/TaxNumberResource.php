<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 14:53:19 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use App\Models\Helpers\TaxNumber;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxNumberResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var TaxNumber $taxNumber */
        $taxNumber = $this;

        return [
            'id'                         => $taxNumber->id,
            'number'                     => $taxNumber->number,
            'type'                       => $taxNumber->type,
            'country_id'                 => $taxNumber->country_id,
            'country'                    => $taxNumber->country_id ? CountryResource::make($taxNumber->country) : null,
            'status'                     => $taxNumber->status,
            'valid'                      => $taxNumber->valid,
            'data'                       => $taxNumber->data,
            'checked_at'                 => $taxNumber->checked_at,
            'invalid_checked_at'         => $taxNumber->invalid_checked_at,
            'external_service_failed_at' => $taxNumber->external_service_failed_at,
            'validation_type'            => $taxNumber->validation_type,


        ];
    }
}
