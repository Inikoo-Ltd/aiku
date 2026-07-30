<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 07:50:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Http\Resources\HumanResources\ClockingMachineQRCodeResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use Lorisleiva\Actions\ActionRequest;
use Exception;
use Illuminate\Http\JsonResponse;

class GenerateClockingMachineQrCode extends OrgAction
{
    public function handle(ClockingMachine $clockingMachine, array $modelData): ClockingMachineQRCode
    {
        /** @var ClockingMachineQRCode $clockingMachineQRCode */
        $clockingMachineQRCode = StoreClockingMachineQRCode::run($clockingMachine, $modelData);

        return $clockingMachineQRCode;
    }


    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:64']
        ];
    }

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): JsonResponse
    {
        $this->initialisation($clockingMachine->organisation, $request);


        try {
            $clockingMachineQRCode = $this->handle($clockingMachine, $this->validatedData);

            return response()->json([
                'success' => true,
                'data'    => new ClockingMachineQRCodeResource($clockingMachineQRCode)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
