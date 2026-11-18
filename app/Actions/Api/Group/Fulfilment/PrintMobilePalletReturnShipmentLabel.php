<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Dispatching\Printer\PrintShipmentLabel;
use App\Models\Dispatching\Shipment;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PrintMobilePalletReturnShipmentLabel
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws ValidationException
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, Shipment $shipment, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWorkPalletReturn($user, $warehouse, $palletReturn);
        $this->ensureShipmentOfReturn($palletReturn, $shipment);

        if (!$this->canPrintLabels($user)) {
            throw ValidationException::withMessages(['printer' => __('Set a preferred printer on the web first.')]);
        }

        $this->changePalletReturn($palletReturn, function () use ($shipment, $user) {
            try {
                PrintShipmentLabel::make()->handle($shipment, $user);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['printer' => collect($exception->errors())->flatten()->first()]);
            }
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', __('Label sent to the printer.'));
    }
}
