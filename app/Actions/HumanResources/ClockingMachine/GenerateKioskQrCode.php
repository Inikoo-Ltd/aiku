<?php


/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use Exception;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateKioskQrCode
{
    use AsAction;
    use WithClockingKioskToken;

    public function asController(string $kioskToken, ActionRequest $request): JsonResponse
    {
        $clockingMachine = $this->resolveKioskMachine($kioskToken);

        try {
            return response()->json([
                'success' => true,
                'data'    => GenerateClockingMachineQrCode::make()->handle($clockingMachine),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
