<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 22:18:39 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\OrgPaymentServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgPaymentServiceProviderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var OrgPaymentServiceProvider $paymentServiceProvider */
        $paymentServiceProvider = $this->resource;

        return [
            'slug' => $paymentServiceProvider->slug,
            'code' => $paymentServiceProvider->code,
            'created_at' => $paymentServiceProvider->created_at,
        ];
    }
}
