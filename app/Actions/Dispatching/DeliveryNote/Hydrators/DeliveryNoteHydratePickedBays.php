<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Mar 2026 10:42:44 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydratePickedBays implements ShouldBeUnique
{
    use AsAction;
    use WithActionUpdate;

    public function getJobUniqueId(?int $deliveryNoteId): string
    {
        return (string)($deliveryNoteId ?? 'empty');
    }

    public function handle(?int $deliveryNoteId): void
    {
        if (!$deliveryNoteId) {
            return;
        }

        $deliveryNote = DeliveryNote::find($deliveryNoteId);
        if (!$deliveryNote) {
            return;
        }

        $pickedBays     = [];
        $sortPickedBays = '';
        foreach ($deliveryNote->pickedBays()->orderByRaw('picked_bays.code')->get() as $pickedBay) {
            $pickedBays[]   = [
                'id'   => $pickedBay->id,
                'slug' => $pickedBay->slug,
                'name' => $pickedBay->code,
            ];
            $sortPickedBays .= $pickedBay->code.' ';
        }
        DeliveryNote::where('id', $deliveryNoteId)->update([
            'sort_picked_bays' => trim($sortPickedBays),
            'data->picked_bays' => $pickedBays ?: null,
        ]);
    }


}
