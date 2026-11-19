<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-16h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteWorkAuthorisation;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FinaliseAndDispatchDeliveryNote extends OrgAction
{
    use WithDeliveryNoteWorkAuthorisation;
    use WithUnprintedLeafletsGuard;
    use WithAlreadyDispatchedGuard;

    /**
     * @throws \Throwable
     */
    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return DB::transaction(function () use ($deliveryNote) {
            if ($this->isDispatchedUnderLock($deliveryNote)) {
                return $deliveryNote->refresh();
            }

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
        $this->initialisationFromShop($deliveryNote->shop, $request);

        if ($deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED) {
            return null;
        }

        if ($notification = $this->unprintedLeafletsNotification($deliveryNote, __('Every insert must be printed before finalising and dispatching.'))) {
            return $notification;
        }

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
