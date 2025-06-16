<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Space\SpaceStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Space;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerHydrateSpaces implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(FulfilmentCustomer $fulfilmentCustomer): string
    {
        return $fulfilmentCustomer->id;
    }

    public function handle(FulfilmentCustomer $fulfilmentCustomer): void
    {
        $stats = [
            'number_spaces' => $fulfilmentCustomer->spaces()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'spaces',
            field: 'state',
            enum: SpaceStateEnum::class,
            models: Space::class,
            where: function ($q) use ($fulfilmentCustomer) {
                $q->where('fulfilment_customer_id', $fulfilmentCustomer->id);
            }
        ));


        $fulfilmentCustomer->update($stats);
    }
}
