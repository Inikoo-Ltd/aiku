<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\Printer\PrintShipmentLabel;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PrintMobileShipmentLabel
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobilePrinters;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, Shipment $shipment, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanShip($deliveryNote, $user);
        $this->ensureShipmentOfNote($deliveryNote, $shipment);

        if (!$this->canPrintLabels($user)) {
            throw ValidationException::withMessages(['printer' => __('Set a preferred printer on the web first.')]);
        }

        try {
            PrintShipmentLabel::make()->handle($shipment, $user);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['printer' => collect($exception->errors())->flatten()->first()]);
        }

        return $this->respondWith($deliveryNote, $user, 'done', __('Label sent to the printer.'));
    }
}
