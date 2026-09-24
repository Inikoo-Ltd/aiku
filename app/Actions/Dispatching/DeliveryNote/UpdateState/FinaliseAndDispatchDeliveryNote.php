<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-16h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FinaliseAndDispatchDeliveryNote extends OrgAction
{
    use WithUnprintedLeafletsGuard;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return DB::transaction(function () use ($deliveryNote) {

            $finalisedDeliveryNote = FinaliseDeliveryNote::make()->action($deliveryNote);

            $finalisedDeliveryNote->refresh();
            $dispatchedDeliveryNote = DispatchDeliveryNote::make()->action($deliveryNote);

            $dispatchedDeliveryNote->refresh();

            return $dispatchedDeliveryNote;
        });

    }

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): ?RedirectResponse
    {
        if ($notification = $this->unprintedLeafletsNotification($deliveryNote, __('Every insert must be printed before finalising and dispatching.'))) {
            return $notification;
        }

        $this->initialisationFromShop($deliveryNote->shop, $request);

        $this->handle($deliveryNote);

        return null;
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
