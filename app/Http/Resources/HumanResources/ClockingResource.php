<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Http\Resources\HasSelfCall;
use App\Models\HumanResources\Clocking;
use Illuminate\Http\Resources\Json\JsonResource;

class ClockingResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Clocking $clocking */
        $clocking = $this;

        return [
            'id'                    => $clocking->id,
            'type'                  => $clocking->type,
            'notes'                 => $clocking->notes,
            'workplace_slug'        => $clocking->workplace->slug,
            'clocked_at'            => $clocking->clocked_at,
            'clocking_machine_slug' => $clocking->clockingMachine->slug,
            'employee'              => EmployeeResource::make($clocking->subject),
            'photo'                 => $clocking->imageSources()
        ];
    }
}
