<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 10:18:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Models\Fulfilment\Pallet;
use App\Rules\PalletIsPhysical;
use Illuminate\Validation\ValidationException;

trait WithVirtualPalletGuard
{
    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function assertPalletIsPhysical(Pallet $pallet, string $attribute = 'pallet'): void
    {
        if ($pallet->isVirtual()) {
            throw ValidationException::withMessages([$attribute => PalletIsPhysical::message()]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function assertVirtualPalletIsEmpty(Pallet $pallet): void
    {
        if ($pallet->isVirtual() && $pallet->hasStoredItemsInStock()) {
            throw ValidationException::withMessages([
                'pallet' => __('A virtual pallet can only be deleted when it is empty.')
            ]);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function assertVirtualPalletCanMoveLocation(Pallet $pallet, ?int $newLocationId): void
    {
        if (!$pallet->isVirtual() || $newLocationId === null || $newLocationId === $pallet->location_id) {
            return;
        }

        if ($pallet->hasStoredItemsInStock()) {
            throw ValidationException::withMessages([
                'location_id' => __('A virtual pallet can only be moved to another location when it is empty, move its SKOs with an audit instead.')
            ]);
        }
    }
}
