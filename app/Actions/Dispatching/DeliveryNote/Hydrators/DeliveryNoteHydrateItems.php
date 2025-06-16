<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Jun 2025 15:37:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydrateItems implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(DeliveryNote $deliveryNote): string
    {
        return $deliveryNote->id;
    }

    public function handle(DeliveryNote $deliveryNote): void
    {
        $stats = [
            'number_items' => $deliveryNote->deliveryNoteItems()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'items',
                field: 'state',
                enum: DeliveryNoteStateEnum::class,
                models: DeliveryNoteItem::class,
                where: function ($q) use ($deliveryNote) {
                    $q->where('delivery_note_id', $deliveryNote->id);
                }
            )
        );


        $deliveryNote->update($stats);
    }


}
