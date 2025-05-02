<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 15:03:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\Traits\WithActionUpdate;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Facades\Cache;

class GetClockingMachineAppQRCode
{
    use WithActionUpdate;


    public function handle(ClockingMachine $clockingMachine): array
    {
        $code = $clockingMachine->slug . '-' . rand(0000, 9999);
        Cache::put('clocking-machine-app-qr-code:'.$code, $clockingMachine->id, 120);

        return [
            'code' => $code
        ];
    }

    public function asController(ClockingMachine $clockingMachine): array
    {
        $this->validateAttributes();
        return $this->handle($clockingMachine);
    }
}
