<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 09 Feb 2022 15:04:15 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePacker;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePicker;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydratePickingBays;
use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateTrolleys;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\Concerns\AsCommand;

class RepairDeliveryNotesTrolleysPickingBays
{
    use AsCommand;

    public string $commandSignature = 'repair:delivery_notes_trolleys_and_picking_bays';


    public function asCommand(): void
    {
        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        /** @var DeliveryNote $deliveryNote */
        foreach (
            DeliveryNote::whereIn('shop_id', $aikuShops)->whereNotIn(
                'state',
                [
                    DeliveryNoteStateEnum::DISPATCHED,
                    DeliveryNoteStateEnum::CANCELLED,
                ]
            )->get() as $deliveryNote
        ) {
            DeliveryNoteHydratePickingBays::run($deliveryNote->id);
            DeliveryNoteHydrateTrolleys::run($deliveryNote->id);
            DeliveryNoteHydratePicker::run($deliveryNote->id);
            DeliveryNoteHydratePacker::run($deliveryNote->id);


        }
    }
}
