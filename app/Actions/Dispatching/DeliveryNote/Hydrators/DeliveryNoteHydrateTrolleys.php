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

class DeliveryNoteHydrateTrolleys implements ShouldBeUnique
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

        $trolleys = [];
        foreach ($deliveryNote->trolleys as $trolley) {
            $trolleys[] = [
                'id'   => $trolley->id,
                'slug' => $trolley->slug,
                'name' => $trolley->name,
            ];
        }

        $lock = Cache::lock('delivery_note_data_update_'.$deliveryNoteId, 10);

        try {
            $lock->block(5, function () use ($deliveryNote, $trolleys) {
                $data = $deliveryNote->data ?? [];

                if (empty($trolleys)) {
                    unset($data['trolleys']);
                } else {
                    $data['trolleys'] = $trolleys;
                }

                $deliveryNote->update(['data' => $data]);
            });
        } catch (LockTimeoutException) {
            return;
        }
    }


}
