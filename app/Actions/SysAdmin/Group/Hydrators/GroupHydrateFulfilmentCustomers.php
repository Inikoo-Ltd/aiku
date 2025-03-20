<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateFulfilmentCustomers
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public string $jobQueue = 'low-priority';
    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_customers_interest_pallets_storage' => $group->fulfilmentCustomers()->where('pallets_storage', true)->count(),
            'number_customers_interest_items_storage'   => $group->fulfilmentCustomers()->where('items_storage', true)->count(),
            'number_customers_interest_dropshipping'    => $group->fulfilmentCustomers()->where('dropshipping', true)->count(),
        ];

        $group->fulfilmentStats()->update($stats);
    }

}
