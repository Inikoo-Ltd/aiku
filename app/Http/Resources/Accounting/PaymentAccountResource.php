<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Mar 2024 17:52:44 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\PaymentAccount;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PaymentAccount $paymentAccount */
        $paymentAccount = $this;

        return [
            'slug' => $paymentAccount->slug,
            'name' => $paymentAccount->name,
            'number_payments' => $paymentAccount->stats->number_payments,
            'code' => $paymentAccount->code,
            'created_at' => $paymentAccount->created_at,
            'updated_at' => $paymentAccount->updated_at,
        ];
    }
}
