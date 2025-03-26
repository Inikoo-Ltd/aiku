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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class FulfilmentCustomerHydrateSpaces
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    private FulfilmentCustomer $fulfilmentCustomer;
    public function __construct(FulfilmentCustomer $fulfilmentCustomer)
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->fulfilmentCustomer->id))->dontRelease()];
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
