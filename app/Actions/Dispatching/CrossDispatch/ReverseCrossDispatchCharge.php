<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Enums\Dispatching\CrossDispatch\CrossDispatchChargeStateEnum;
use App\Models\Dispatching\CrossDispatchCharge;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\Concerns\AsObject;

class ReverseCrossDispatchCharge
{
    use AsObject;

    /**
     * INI-484 C1. Undoing the dispatch of a note, from the note or from the order page, reverses its open charge, so a
     * second dispatch brings it back instead of charging twice. Goods returned later are not a reversal: the charge
     * keeps each line's unit cost, so a return is credited from it.
     */
    public function handle(DeliveryNote $deliveryNote): void
    {
        CrossDispatchCharge::where('delivery_note_id', $deliveryNote->id)
            ->where('state', CrossDispatchChargeStateEnum::OPEN)
            ->update(['state' => CrossDispatchChargeStateEnum::REVERSED, 'reversed_at' => now(), 'updated_at' => now()]);
    }
}
