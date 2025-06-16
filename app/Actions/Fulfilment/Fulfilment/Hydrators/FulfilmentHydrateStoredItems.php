<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 21:49:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateStoredItems implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Fulfilment $fulfilment): string
    {
        return $fulfilment->id;
    }

    public function handle(Fulfilment $fulfilment): void
    {
        $stats = [
            'number_stored_items' => StoredItem::where('fulfilment_id', $fulfilment->id)->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'stored_items',
                field: 'state',
                enum: StoredItemStateEnum::class,
                models: StoredItem::class,
                where: function ($q) use ($fulfilment) {
                    $q->where('fulfilment_id', $fulfilment->id);
                }
            )
        );

        $fulfilment->stats()->update($stats);
    }
}
