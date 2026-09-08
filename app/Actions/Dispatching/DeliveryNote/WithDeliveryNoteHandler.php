<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Models\Dispatching\DeliveryNote;

trait WithDeliveryNoteHandler
{
    /**
     * How long a temporary claim on a delivery note survives without the holder touching it.
     * Picking a full note is measured in tens of minutes and a picker can be away from the
     * screen for most of it, so this is generous on purpose: the claim only ever lets someone
     * act on the one note they are physically working.
     */
    public const TEMP_HANDLING_MINUTES = 60;

    /**
     * While the note is being picked the picker owns it, even if a packer is already recorded
     * from an earlier pass. From packing onwards the packer owns it and keeps that right after
     * the note leaves packing, so a batch code or a miscount can still be corrected on a packed
     * or finalised note. Anyone else needs a temporary claim, which slides forward each time it
     * is used so that it cannot expire underneath somebody who is still working.
     */
    protected function canHandleDeliveryNote(?DeliveryNote $deliveryNote): bool
    {
        if (!$deliveryNote) {
            return false;
        }

        $handler = $deliveryNote->state->isPickedOrLater()
            ? $deliveryNote->packer_user_id ?? $deliveryNote->picker_user_id
            : $deliveryNote->picker_user_id;

        if ($handler && $handler == request()->user()?->id) {
            return true;
        }

        $tempHandler = session('temp_handling_delivery_note') ?? [];

        if (data_get($tempHandler, 'value') != $deliveryNote->id) {
            return false;
        }

        if (now()->gte(data_get($tempHandler, 'expires_at'))) {
            return false;
        }

        self::claimDeliveryNoteHandling($deliveryNote);

        return true;
    }

    public static function claimDeliveryNoteHandling(DeliveryNote $deliveryNote): void
    {
        session()->put('temp_handling_delivery_note', [
            'value'      => $deliveryNote->id,
            'expires_at' => now()->addMinutes(self::TEMP_HANDLING_MINUTES),
        ]);
    }
}
