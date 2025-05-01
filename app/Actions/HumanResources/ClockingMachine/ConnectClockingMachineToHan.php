<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 May 2024 15:37:54 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineStatusEnum;
use App\Http\Resources\HumanResources\ClockingMachineResource;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ConnectClockingMachineToHan
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(ClockingMachine $clockingMachine, array $modelData): array
    {
        data_set($modelData, 'status', ClockingMachineStatusEnum::CONNECTED->value);

        $this->update($clockingMachine, $modelData);

        return [
            'token' => $clockingMachine->createToken(Arr::get($modelData, 'device_name', 'unknown-device'))->plainTextToken,
            'data'  => ClockingMachineResource::make($clockingMachine)
        ];
    }

    public function rules(): array
    {
        return [
            'qr_code'     => ['required', 'string', 'exists:clocking_machines,qr_code'],
            'device_name' => ['required', 'string'],
            'device_uuid' => ['required', 'string', 'unique:clocking_machines,device_uuid'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'id.required' => __('Invalid QR Code'),
        ];
    }


    public function asController(ActionRequest $request): array
    {
        $this->fillFromRequest($request);
        $validatedData   = $this->validateAttributes();
        $clockingMachine = ClockingMachine::where('qr_code', $validatedData['qr_code'])->first();

        return $this->handle($clockingMachine, Arr::only($validatedData, ['device_name', 'device_uuid']));
    }
}
