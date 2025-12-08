<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 12:06:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Models\Production\JobOrder;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var JobOrder $jobOrder */
        $jobOrder = $this;

        return [
            'reference' => $jobOrder->reference,
            'state' => $jobOrder->state,
            'date' => $jobOrder->date,
            'notes' => [
                'customer' => $jobOrder->customer_notes,
                'public' => $jobOrder->public_notes,
                'internal' => $jobOrder->internal_notes,
            ],
        ];
    }
}
