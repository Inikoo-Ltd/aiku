<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:03:06 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Models\HumanResources\ClockingMachine;
use Lorisleiva\Actions\Concerns\AsObject;

class GetClockingMachineShowcase
{
    use AsObject;

    public function handle(ClockingMachine $clockingMachine): array
    {
        return [
            'slug'              => $clockingMachine->slug,
            'name'              => $clockingMachine->name,
            'type'              => $clockingMachine->type,
            'qr_code'           => $clockingMachine->qr_code,
            'status'            => $clockingMachine->status,
            'device_name'       => $clockingMachine->device_name,
            'device_uuid'       => $clockingMachine->device_uuid,
            'kiosk_token'       => $clockingMachine->kiosk_token,
            'kiosk_url'         => $clockingMachine->kiosk_token
                ? route('grp.kiosk.show', ['kioskToken' => $clockingMachine->kiosk_token])
                : null,
        ];
    }
}
