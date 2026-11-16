<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

        $handler = self::deliveryNoteHandlerId($deliveryNote);

        if ($handler && $handler == request()->user()?->id) {
            return true;
        }

        $tempHandler = session('temp_handling_delivery_note') ?? [];

        if (data_get($tempHandler, 'value') == $deliveryNote->id && now()->lt(data_get($tempHandler, 'expires_at'))) {
            self::claimDeliveryNoteHandling($deliveryNote);

            return true;
        }

        return self::useMobileDeliveryNoteClaim($deliveryNote, request()->user());
    }

    public static function mobileDeliveryNoteClaimKey(User $user): string
    {
        return 'mobile_handling_delivery_note:'.$user->id;
    }

    public static function claimMobileDeliveryNoteHandling(DeliveryNote $deliveryNote, User $user): void
    {
        $expiresAt = now()->addMinutes(self::TEMP_HANDLING_MINUTES);

        Cache::put(self::mobileDeliveryNoteClaimKey($user), [
            'delivery_note_id' => $deliveryNote->id,
            'expires_at'       => $expiresAt->toIso8601String(),
        ], $expiresAt);
    }

    public static function useMobileDeliveryNoteClaim(DeliveryNote $deliveryNote, mixed $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $claim = Cache::get(self::mobileDeliveryNoteClaimKey($user));

        if (data_get($claim, 'delivery_note_id') != $deliveryNote->id || !data_get($claim, 'expires_at')) {
            return false;
        }

        if (now()->gte(Carbon::parse(data_get($claim, 'expires_at')))) {
            return false;
        }

        self::claimMobileDeliveryNoteHandling($deliveryNote, $user);

        return true;
    }

    public static function deliveryNoteHandlerId(DeliveryNote $deliveryNote): ?int
    {
        return $deliveryNote->state->isPickedOrLater()
            ? $deliveryNote->packer_user_id ?? $deliveryNote->picker_user_id
            : $deliveryNote->picker_user_id;
    }

    public static function claimDeliveryNoteHandling(DeliveryNote $deliveryNote): void
    {
        session()->put('temp_handling_delivery_note', [
            'value'      => $deliveryNote->id,
            'expires_at' => now()->addMinutes(self::TEMP_HANDLING_MINUTES),
        ]);
    }
}
