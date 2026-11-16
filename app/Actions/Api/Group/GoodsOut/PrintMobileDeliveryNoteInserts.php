<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteLeaflet\PrintDeliveryNoteLeaflet;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PrintMobileDeliveryNoteInserts
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobilePrinters;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);

        $printerId = $this->insertPrinterId($user);

        if ($printerId === null) {
            throw ValidationException::withMessages(['printer' => __('Set a leaflet printer or a preferred printer on the web first.')]);
        }

        if (!$this->canPrintAllInserts($deliveryNote, $user)) {
            throw ValidationException::withMessages(['inserts' => __('Every insert is already printed.')]);
        }

        $printed = 0;
        foreach ($deliveryNote->unprintedLeaflets()->orderBy('id')->get() as $leaflet) {
            try {
                PrintDeliveryNoteLeaflet::make()->set('printerId', $printerId)->handle($leaflet);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['printer' => collect($exception->errors())->flatten()->first()]);
            }
            $printed++;
        }

        return $this->respondWith($deliveryNote, $user, 'done', trans_choice(':count insert sent to the printer.|:count inserts sent to the printer.', $printed, ['count' => $printed]));
    }
}
