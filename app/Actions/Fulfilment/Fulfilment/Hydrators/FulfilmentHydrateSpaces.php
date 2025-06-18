<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\Fulfilment\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\Space;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentHydrateSpaces implements ShouldBeUnique
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
            'number_spaces' => $fulfilment->spaces()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'spaces',
            field: 'state',
            enum: SpaceStateEnum::class,
            models: Space::class,
            where: function ($q) use ($fulfilment) {
                $q->where('fulfilment_id', $fulfilment->id);
            }
        ));

        $fulfilment->stats()->update($stats);
    }

}
