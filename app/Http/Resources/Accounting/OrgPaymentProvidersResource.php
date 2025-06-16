<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Accounting;

use App\Actions\UI\Accounting\Traits\HasPaymentServiceProviderFields;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgPaymentProvidersResource extends JsonResource
{
    use HasPaymentServiceProviderFields;

    public function toArray($request): array
    {
        return [
            'code'                        => $this->code,
            'name'                        => $this->name,
            'state'                       => $this->state,
        ];
    }
}
