<?php

/*
 * Author: Stewicca <wiccaalf@gmail.com>
 * Created: Mon, 02 Jun 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydrateDispatchTotals implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(DeliveryNote $deliveryNote): string
    {
        return $deliveryNote->id;
    }

    public function handle(DeliveryNote $deliveryNote): void
    {
        $totalSkos = 0;
        $totalUnits = 0;

        foreach ($deliveryNote->deliveryNoteItems()->with('orgStock')->get() as $item) {
            $qty = (float) $item->quantity_dispatched;
            $totalSkos += $qty;
            $packedIn = $item->orgStock?->packed_in ?? 1;
            $totalUnits += $qty * $packedIn;
        }

        $deliveryNote->update([
            'total_skos'  => (int) $totalSkos,
            'total_units' => (int) $totalUnits,
        ]);
    }
}
