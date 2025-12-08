<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use App\Models\HumanResources\ClockingMachine;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class ClockingMachineResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var ClockingMachine $clockingMachine */
        $clockingMachine = $this;

        return [
            'id' => $clockingMachine->id,
            'organisation_id' => $clockingMachine->organisation_id,
            'organisation_slug' => $clockingMachine->organisation->slug,
            'workplace_id' => $clockingMachine->workplace_id,
            'workplace_slug' => $clockingMachine->workplace->slug,
            'workplace_name' => $clockingMachine->workplace->name,
            'slug' => $clockingMachine->slug,
            'qr_code' => $clockingMachine->qr_code,
            'status' => $clockingMachine->status,
            'name' => $clockingMachine->name,
            'type' => $clockingMachine->type,
            'device_name' => $clockingMachine->device_name,
            'device_uuid' => $clockingMachine->device_uuid,
            'created_at' => $clockingMachine->created_at,
            'updated_at' => $clockingMachine->updated_at,
            'nfc_tag' => Arr::get($clockingMachine->data, 'nfc_tag'),
        ];
    }
}
