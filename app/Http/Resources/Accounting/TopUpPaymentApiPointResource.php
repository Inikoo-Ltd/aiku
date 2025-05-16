<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 22:26:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Http\Resources\HasSelfCall;
use App\Models\Accounting\TopUpPaymentApiPoint;
use Illuminate\Http\Resources\Json\JsonResource;

class TopUpPaymentApiPointResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var TopUpPaymentApiPoint $topUpPaymentApiPoint */
        $topUpPaymentApiPoint = $this;


        return [
            'id'               => $topUpPaymentApiPoint->id,
            'data'             => $topUpPaymentApiPoint->data,

        ];
    }
}
