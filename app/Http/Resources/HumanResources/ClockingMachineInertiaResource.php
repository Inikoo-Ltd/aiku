<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 22 Jan 2022 03:06:40 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\ClockingMachine;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ClockingMachineInertiaResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var ClockingMachine $clockingMachine */
        $clockingMachine = $this;

        return [
            'id' => $clockingMachine->id,
            'slug' => $clockingMachine->slug,
            'code' => $clockingMachine->code,
            'workplace_id' => $clockingMachine->workplace_id,
        ];
    }
}
