<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-16h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FinaliseAndDispatchDeliveryNote extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        $deliveryNote = DB::transaction(function () use ($deliveryNote) {
            $finalisedDeliveryNote = FinaliseDeliveryNote::make()->action($deliveryNote);
            $finalisedDeliveryNote->refresh();
            $dispatchedDeliveryNote = DispatchDeliveryNote::make()->action($deliveryNote);
            $dispatchedDeliveryNote->refresh();

            return $dispatchedDeliveryNote;
        });

        return $deliveryNote;
    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request)
    {
        $this->initialisationFromShop($deliveryNote->shop, $request);

        $this->handle($deliveryNote);
    }

    /**
     * @throws \Throwable
     */
    public function action(DeliveryNote $deliveryNote): DeliveryNote
    {
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote);
    }
}
