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

        $trolleys     = [];
        $sortTrolleys = '';
        foreach ($deliveryNote->trolleys()->orderByRaw('trolleys.name')->get() as $trolley) {
            $trolleys[]   = [
                'id'   => $trolley->id,
                'slug' => $trolley->slug,
                'name' => $trolley->name,
            ];
            $sortTrolleys .= $trolley->name.' ';
        }
        DeliveryNote::where('id', $deliveryNoteId)->update([
            'sort_trolleys' => trim($sortTrolleys),
            'data->trolleys' => $trolleys ?: null,
        ]);
    }


}
