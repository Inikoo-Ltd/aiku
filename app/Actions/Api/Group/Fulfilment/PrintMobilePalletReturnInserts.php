<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturnLeaflet\PrintPalletReturnLeaflet;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class PrintMobilePalletReturnInserts
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws ValidationException
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanHandlePalletReturn($user, $warehouse, $palletReturn);

        $printerId = $this->insertPrinterId($user);
        if ($printerId === null) {
            throw ValidationException::withMessages(['printer' => __('Set a leaflet printer or a preferred printer on the web first.')]);
        }

        $printed = $this->changePalletReturn($palletReturn, function (PalletReturn $palletReturn) use ($printerId) {
            $leaflets = $palletReturn->unprintedLeaflets()->orderBy('id')->get();
            if ($leaflets->isEmpty()) {
                $this->refuseState(__('Every insert is already printed.'));
            }

            foreach ($leaflets as $leaflet) {
                try {
                    PrintPalletReturnLeaflet::make()->set('printerId', $printerId)->handle($leaflet);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['printer' => collect($exception->errors())->flatten()->first()]);
                }
            }

            return $leaflets->count();
        });

        return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'done', trans_choice(':count insert sent to the printer.|:count inserts sent to the printer.', $printed, ['count' => $printed]));
    }
}
