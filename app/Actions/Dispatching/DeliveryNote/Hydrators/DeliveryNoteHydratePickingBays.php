<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Mar 2026 10:42:44 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydratePickingBays implements ShouldBeUnique
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

        $pickedBays = [];
        foreach ($deliveryNote->pickedBays as $pickedBay) {
            $pickedBays[] = [
                'id'   => $pickedBay->id,
                'slug' => $pickedBay->slug,
                'name' => $pickedBay->code,
            ];
        }

        $lock = Cache::lock('delivery_note_data_update_'.$deliveryNoteId, 10);

        try {
            $lock->block(5, function () use ($deliveryNote, $pickedBays) {
                $data = $deliveryNote->data ?? [];

                if (empty($pickedBays)) {
                    unset($data['picking_bays']);
                } else {
                    $data['picking_bays'] = $pickedBays;
                }

                $deliveryNote->update(['data' => $data]);
            });
        } catch (LockTimeoutException) {
            return;
        }
    }


}
