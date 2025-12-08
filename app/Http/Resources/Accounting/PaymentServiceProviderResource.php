<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 22:13:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentServiceProviderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PaymentServiceProvider $paymentServiceProvider */
        $paymentServiceProvider = $this->resource;

        return [
            'slug' => $paymentServiceProvider->slug,
            'code' => $paymentServiceProvider->code,
            'name' => $paymentServiceProvider->name,
            'created_at' => $paymentServiceProvider->created_at,
        ];
    }
}
