<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 23:49:32 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\TopUp;
use Illuminate\Http\Resources\Json\JsonResource;

class TopUpResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TopUp $topUp */
        $topUp = $this;

        return [
            'slug'      => $topUp->slug,
            'reference' => $topUp->reference,
            'amount'    => $topUp->amount,
            'status'    => $topUp->status
        ];
    }
}
